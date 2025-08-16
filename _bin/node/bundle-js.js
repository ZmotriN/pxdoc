const fs = require('fs');
const path = require('path');
const esbuild = require('esbuild');

// === DÉTERMINER LE RÉPERTOIRE DU SCRIPT ===
const scriptDir = __dirname;

// === CONFIGURATION (relatif au script) ===
const inputFiles = [
    '../../jscripts/highlight.min.js',
    '../../jscripts/swiper-bundle.min.js',
    '../../jscripts/vue.global.prod.js',
    '../../jscripts/pxdoc.min.js'
].map(file => path.join(scriptDir, file));

const bannerFile = path.join(scriptDir, 'banner.txt');
const tempFile = path.join(scriptDir, '../../jscripts/__temp-bundle.js');
const outputFile = path.join(scriptDir, '../../jscripts/bundle.min.js');

// === LIRE LE BANNER ===
let bannerText = '';
try {
    bannerText = fs.readFileSync(bannerFile, 'utf8');
    console.log(`📎 Banner lu depuis ${bannerFile}`);
} catch (err) {
    console.warn(`⚠️ Avertissement: Impossible de lire ${bannerFile}, aucun banner ne sera ajouté.`);
}

// === CONCATÉNER LES FICHIERS ===
try {
    const combined = inputFiles
        .map(file => {
            const content = fs.readFileSync(file, 'utf8');
            return `// === ${path.basename(file)} ===\n${content}`;
        })
        .join('\n\n');

    fs.mkdirSync(path.dirname(tempFile), { recursive: true });
    fs.writeFileSync(tempFile, combined, 'utf8');
    console.log(`✅ Fichier temporaire créé: ${tempFile}`);
} catch (err) {
    console.error('❌ Erreur de concaténation :', err.message);
    process.exit(1);
}

// === MINIFICATION AVEC BANNER ===
esbuild.build({
    legalComments: 'none',
    entryPoints: [tempFile],
    treeShaking: true,
    outfile: outputFile,
    bundle: false,
    minify: true,
    target: 'es2020',
    banner: {
        js: bannerText,
    },
}).then(() => {
    fs.unlinkSync(tempFile); // Nettoyage
    console.log(`✅ Bundle final généré: ${outputFile}`);
}).catch((err) => {
    console.error('❌ Erreur ESBuild :', err.message);
    process.exit(1);
});
