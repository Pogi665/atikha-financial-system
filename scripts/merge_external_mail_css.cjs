// Preserve every existing CSS rule while adding newly generated Tailwind utilities.
// First build with npm run build:css -- --output .migration-private/external-email-tailwind-generated.css
const fs = require('fs');
const path = require('path');
const postcss = require('postcss');
const root = path.resolve(__dirname, '..');
const target = path.join(root, 'assets/css/tailwind.css');
const generated = path.join(root, '.migration-private/external-email-tailwind-generated.css');
const original = fs.readFileSync(target, 'utf8');
const existing = new Set();
function key(node, context) {
    return context + '/' + (node.type === 'rule' ? node.selector : node.name + ':' + node.params);
}
function inventory(nodes, context) {
    for (const node of nodes || []) {
        if (node.type === 'rule') existing.add(key(node, context));
        else if (node.type === 'atrule') {
            if (node.nodes) inventory(node.nodes, key(node, context));
            else existing.add(key(node, context));
        }
    }
}
inventory(postcss.parse(original).nodes, '');
let added = 0;
function missing(nodes, context) {
    const result = [];
    for (const node of nodes || []) {
        if (node.type === 'rule' && !existing.has(key(node, context))) {
            result.push(node.clone()); added++;
        } else if (node.type === 'atrule') {
            if (node.nodes) {
                const children = missing(node.nodes, key(node, context));
                if (children.length) { const copy = node.clone(); copy.removeAll(); copy.append(children); result.push(copy); }
            } else if (!existing.has(key(node, context))) result.push(node.clone());
        }
    }
    return result;
}
const additions = missing(postcss.parse(fs.readFileSync(generated, 'utf8')).nodes, '');
if (additions.length) fs.writeFileSync(target, original + '\n/* External Email utilities; existing styles preserved. */\n' + additions.map(node => node.toString()).join('') + '\n');
if (!fs.readFileSync(target, 'utf8').startsWith(original)) throw new Error('Existing stylesheet was modified');
process.stdout.write('PASS: added ' + added + ' Tailwind rules; all existing CSS preserved verbatim.\n');
