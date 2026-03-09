const webpack = require('webpack');
const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');

const args = process.argv.slice(2);
if (args.length < 2) {
    console.error('Usage: node obfuscate.js <source> <output>');
    process.exit(1);
}

const sourcePath = path.resolve(args[0]);
const outputPath = path.resolve(args[1]);
const outputDir = path.dirname(outputPath);
const outputFilename = path.basename(outputPath);

const config = {
    mode: 'production',
    entry: sourcePath,
    output: {
        path: outputDir,
        filename: outputFilename,
        clean: false,
    },
    optimization: {
        minimize: true,
        minimizer: [
            new TerserPlugin({
                extractComments: false,
                terserOptions: {
                    mangle: {
                        toplevel: true,
                    },
                    compress: true,
                },
            }),
        ],
    },
    devtool: 'source-map',
};

webpack(config, (err, stats) => {
    if (err) {
        console.error('Webpack error:', err);
        process.exit(1);
    }

    const info = stats.toJson();

    if (stats.hasErrors()) {
        console.error('Compilation errors:', info.errors);
        process.exit(1);
    }

    if (stats.hasWarnings()) {
        console.warn('Compilation warnings:', info.warnings);
    }

    console.log('Successfully compiled ' + sourcePath + ' to ' + outputPath + ' using Webpack');
});
