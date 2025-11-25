const path = require('path');
const { VueLoaderPlugin } = require('vue-loader');
const ESLintPlugin = require('eslint-webpack-plugin');
const webpack = require("webpack");
const XMLParser = require("fast-xml-parser").XMLParser;
const fs = require("node:fs");

const parser = new XMLParser();
const app = parser.parse(fs.readFileSync(__dirname + "/appinfo/info.xml", "utf-8"))
const appName = app.info.id;
const appVersion = app.info.version;

const config = {
	entry: path.join(__dirname, 'src', 'main.js'),
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: `${appName}.js`,
		chunkFilename: 'chunks/[name]-[hash].js'
	},
	module: {
		rules: [
			{
				test: /\.css$/,
				use: ['style-loader', 'css-loader']
			},
			{
				test: /\.scss$/,
				use: ['style-loader', 'css-loader', 'sass-loader']
			},
			{
				test: /\.vue$/,
				loader: 'vue-loader',
			},
			{
				test: /\.js$/,
				loader: 'babel-loader',
				exclude: /node_modules/
			}
		]
	},
	plugins: [
		new VueLoaderPlugin(),
        new webpack.DefinePlugin({
            appName: JSON.stringify(appName),
            appVersion: JSON.stringify(appVersion),
        }),
		new ESLintPlugin({ extensions: ['js', 'vue'], failOnError: false }),
	],
	resolve: {
		extensions: ['.js', '.mjs', '.vue'],
		symlinks: false
	}
};

module.exports = config;
