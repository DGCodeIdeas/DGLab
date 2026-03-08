const { minify } = require('terser');
const fs = require('fs');
const path = require('path');

const args = process.argv.slice(2);
if (args.length < 2) {
    console.error('Usage: node obfuscate.js <source> <output>');
    process.exit(1);
}

const sourcePath = args[0];
const outputPath = args[1];
const sourceCode = fs.readFileSync(sourcePath, 'utf8');

async function run() {
    try {
        const options = {
            compress: true,
            mangle: true,
            sourceMap: {
                filename: path.basename(outputPath),
                url: path.basename(outputPath) + '.map'
            }
        };

        const result = await minify(sourceCode, options);

        fs.writeFileSync(outputPath, result.code);
        if (result.map) {
            fs.writeFileSync(outputPath + '.map', result.map);
        }

        console.log(`Successfully obfuscated ${sourcePath}`);
    } catch (err) {
        console.error(`Error obfuscating ${sourcePath}:`, err);
        process.exit(1);
    }
}

run();
