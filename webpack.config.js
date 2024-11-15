const Encore = require('@symfony/webpack-encore');
const path = require('path');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');

const directoryName = path.dirname(__filename).split('/').pop();

Encore.setOutputPath('assets/dist')
	.setPublicPath(`/${directoryName}/assets/dist`)
	.setManifestKeyPrefix('')
	.enableSingleRuntimeChunk()
	.enableSourceMaps(!Encore.isProduction())
	.enableVersioning(Encore.isProduction())
	.cleanupOutputBeforeBuild()
	.enableSassLoader()
	.enablePostCssLoader()
	.addPlugin(new DependencyExtractionWebpackPlugin())
	.addStyleEntry('backend-styles', './assets/src/sass/backend.scss')
	// .addEntry('forms', './assets/src/js/forms.js');
	// .addStyleEntry('frontend-styles', './assets/src/sass/frontend.scss')
	// .addEntry('backend-scripts', './assets/src/js/backend.js')
	// .addEntry('frontend-scripts', './assets/src/js/frontend.js')
	// .addEntry('blocks', './assets/src/js/blocks.js');

/**
 * Webpack configuration object.
 *
 * Edit for advanced configs.
 */
const config = Encore.getWebpackConfig();

module.exports = config;
