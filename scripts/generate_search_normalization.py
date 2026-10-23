"""Reproducible canonical NFD mapping for the vendored DataTables search port; no network."""
from pathlib import Path
import json,unicodedata
out=Path(__file__).resolve().parent.parent/'assets/vendor/unicode-search.json'
mapping={chr(i):unicodedata.normalize('NFD',chr(i)) for i in range(0x110000) if not 0xd800<=i<=0xdfff and unicodedata.normalize('NFD',chr(i))!=chr(i)}
out.write_text(json.dumps({'unicode_version':unicodedata.unidata_version,'nfd':mapping},ensure_ascii=False,separators=(',',':')),encoding='utf-8')
print('Canonical decomposition entries:',len(mapping),'Unicode',unicodedata.unidata_version)
