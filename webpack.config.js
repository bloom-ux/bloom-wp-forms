const Encore = require('@symfony/webpack-encore');
const path = require('path');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');

const directoryName = path.dirname(__filename).split('/').pop();

Encore.setOutputPath('assets/dist')
	.setPublicPath(`/assets/dist`)
	.setManifestKeyPrefix('')
	.enableSingleRuntimeChunk()
	.enableSourceMaps(!Encore.isProduction())
	.enableVersioning(Encore.isProduction())
	.cleanupOutputBeforeBuild()
	.enableSassLoader()
	.enablePostCssLoader()
	.addPlugin(new DependencyExtractionWebpackPlugin())
	.addStyleEntry('backend-styles', './assets/src/sass/backend.scss');

/**
 * Webpack configuration object.
 *
 * Edit for advanced configs.
 */
const config = Encore.getWebpackConfig();

module.exports = config;
