const fs = require('fs');
const path = require('path');
const { path: ffmpegPath } = require('@ffmpeg-installer/ffmpeg');

const cwd = process.cwd(); // <-- Répertoire courant où `npm install` est lancé
const binDir = path.resolve(cwd, 'node_modules', '.bin');
const targetPath = path.resolve(ffmpegPath); // chemin absolu vers le binaire réel
const linkPath = path.resolve(binDir, process.platform === 'win32' ? 'ffmpeg.cmd' : 'ffmpeg');

if (!fs.existsSync(binDir)) {
  fs.mkdirSync(binDir, { recursive: true });
}

// Supprime le lien précédent s'il existe
if (fs.existsSync(linkPath)) {
  fs.unlinkSync(linkPath);
}

// Windows : créer un .cmd qui appelle ffmpeg.exe
if (process.platform === 'win32') {
  const cmdContent = `@echo off\r\n"${targetPath}" %*`;
  fs.writeFileSync(linkPath, cmdContent, 'utf8');
} else {
  fs.symlinkSync(targetPath, linkPath);
  fs.chmodSync(linkPath, 0o755);
}

console.log(`✔ ffmpeg linked at ${path.relative(cwd, linkPath)}`);
