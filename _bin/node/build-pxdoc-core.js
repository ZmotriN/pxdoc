// --> Include Libraries
const fs = require('fs');
const path = require('path');
const esbuild = require('esbuild');
const vue = require('vue/package.json');

// --> Set path constants
const scriptDir = __dirname;
const worktDir = path.join(scriptDir, '../../../');
const vueDir = path.join(worktDir, 'node_modules/vue/');
const vueFile = path.join(vueDir, 'dist/vue.global.prod.js')
const pxdocFile = path.join(scriptDir, '../../jscripts/pxdoc.core.js');
const outputFile = path.join(scriptDir, '../../jscripts/pxdoc.core.min.js');

// --> Get Now date
const d = new Date();
const pad = n => String(n).padStart(2, '0');
const now = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;

// --> Load Vue JS
const vueContent = fs.readFileSync(vueFile, 'utf8');

// --> Load PXDoc JS
const pxdocContent = fs.readFileSync(pxdocFile, 'utf8');

// --> Load Banner Content
const bannerContent = fs.readFileSync(path.join(scriptDir, 'banner.txt'), 'utf8')
    .replace(/###DATETIME###/i, now)
    .replace(/###VUEVERSION###/i, vue.version);

// --> Build final plugin
esbuild.build({
    stdin: { contents: [vueContent, pxdocContent].join("\n\n") },
    banner: { js: bannerContent },
    outfile: outputFile,
    legalComments: 'none',
    treeShaking: true,
    bundle: false,
    minify: true,
    target: 'es2020',
}).then(() => {
    console.log(`✅ Bundle final généré: ${outputFile}`);
}).catch((err) => {
    console.error('❌ Erreur ESBuild :', err.message);
    process.exit(1);
});