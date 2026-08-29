const config = require('flarum-webpack-config')();

config.output.uniqueName = require('./package.json').name;

module.exports = config;
