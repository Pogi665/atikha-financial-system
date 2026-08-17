<?php

/**
 * Board communication attachment storage.
 */

const BOARD_UPLOAD_DIR = __DIR__ . '/../uploads/board';
const BOARD_PUBLIC_DIR = 'uploads/board';
const MAX_BOARD_FILE_BYTES = 8 * 1024 * 1024;

const ALLOWED_BOARD_MIMES = [
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
];

function board_upload_error_message(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'That file is too large. Please upload a file under 8 MB.';
        case UPLOAD_ERR_PARTIAL:
            return 'The upload was interrupted. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'Please choose a file first.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
            return 'The server could not store the file. Please contact your administrator.';
        case UPLOAD_ERR_EXTENSION:
            return 'The upload was blocked by the server configuration.';
        default:
            return 'The file could not be uploaded. Please try again.';
    }
}

/**
 * @return array{ok: bool, error: string, path: string, public_path: string,
 *               mime: string, size: int, original: string}
 */
function store_uploaded_board_file(array $file): array
{
    $result = [
        'ok'          => false,
        'error'       => '',
        'path'        => '',
        'public_path' => '',
        'mime'        => '',
        'size'        => 0,
        'original'    => '',
    ];

    $code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) {
        return $result;
    }
    if ($code !== UPLOAD_ERR_OK) {
        $result['error'] = board_upload_error_message($code);

        return $result;
    }

    $tmpPath = $file['tmp_name'] ?? '';
    if (!is_string($tmpPath) || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
        $result['error'] = 'The upload could not be verified. Please try again.';

        return $result;
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        $result['error'] = 'The uploaded file is empty. Please try again.';

        return $result;
    }
    if ($size > MAX_BOARD_FILE_BYTES) {
        $result['error'] = 'That file is too large. Please upload a file under 8 MB.';

        return $result;
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detected = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            if (is_string($detected)) {
                $mime = strtolower($detected);
            }
        }
    }

    if (!isset(ALLOWED_BOARD_MIMES[$mime])) {
        $result['error'] = 'Only PDF, JPG, PNG, DOC, or DOCX files are accepted.';

        return $result;
    }

    if (!is_dir(BOARD_UPLOAD_DIR) && !@mkdir(BOARD_UPLOAD_DIR, 0755, true) && !is_dir(BOARD_UPLOAD_DIR)) {
        error_log('Unable to create board upload directory: ' . BOARD_UPLOAD_DIR);
        $result['error'] = 'The server could not store the file. Please contact your administrator.';

        return $result;
    }

    $filename = bin2hex(random_bytes(16)) . '.' . ALLOWED_BOARD_MIMES[$mime];
    $destination = BOARD_UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpPath, $destination)) {
        error_log('move_uploaded_file failed for destination: ' . $destination);
        $result['error'] = 'The server could not store the file. Please try again.';

        return $result;
    }
    @chmod($destination, 0644);

    $originalName = is_string($file['name'] ?? null) ? basename($file['name']) : '';

    return [
        'ok'          => true,
        'error'       => '',
        'path'        => BOARD_PUBLIC_DIR . '/' . $filename,
        'public_path' => BOARD_PUBLIC_DIR . '/' . $filename,
        'mime'        => $mime,
        'size'        => $size,
        'original'    => substr($originalName, 0, 255),
    ];
}

function board_absolute_path(string $storedPath): string
{
    return BOARD_UPLOAD_DIR . DIRECTORY_SEPARATOR . basename($storedPath);
}

function board_public_url(string $storedPath): string
{
    return BOARD_PUBLIC_DIR . '/' . basename($storedPath);
}
