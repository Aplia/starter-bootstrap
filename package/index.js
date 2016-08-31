'use strict';

var Gulp = require('gulp'),
    Merge = require('merge'),
    MergeStream = require('merge-stream'),
    Path = require('path'),
    Rev = require('gulp-rev'),
    Del = require('del'),
    SourceMaps = require('gulp-sourcemaps'),
    RewriteCSS = require('gulp-rewrite-css'),
    MinifyCss = require('gulp-minify-css'),
    Concat = require('gulp-concat'),
    Uglify = require('gulp-uglify');

var defaultConfig = {
    urlPrefix: '/static',
    staticPath: 'static',
    buildPath: 'build/static',
    // Tasks to run whenever css files has changed
    styleTasks: ['styles', 'manifest'],
    // Tasks to run whenever js files has changed
    scriptTasks: ['scripts', 'manifest'],
};

function cssFlow(config) {
    var dest = config.destPath + '/css';
    return Gulp.src(config.files)
        .pipe(SourceMaps.init())
        .pipe(RewriteCSS({destination: dest}))
        .pipe(Concat(config.destFile))
        .pipe(MinifyCss({compatability: 'ie8'}))
        .pipe(SourceMaps.write('.', {sourceMappingURLPrefix: config.urlPrefix}))
        .pipe(Gulp.dest(config.destPath));
}

function jsFlow(config) {
    if( typeof config.mangle === 'undefined' ) {
        config.mangle = true;
    }

    return Gulp.src(config.files)
        .pipe(SourceMaps.init())
        .pipe(Concat(config.destFile))
        .pipe(Uglify({mangle: config.mangle}))
        .pipe(SourceMaps.write('.', {sourceMappingURLPrefix: config.urlPrefix}))
        .pipe(Gulp.dest(config.destPath));
}

function buildStyles(config) {
    var cssTasks = [],
        config = Merge(defaultConfig, config),
        assets = config.assets || {},
        bundles = assets.bundles;
    for(var prop in bundles) {
        if (bundles[prop].type == 'css') {
            var subPath = Path.dirname(bundles[prop].path);
            if (subPath) {
                subPath = '/' + subPath;
            }
            cssTasks = MergeStream(cssTasks, cssFlow({
                destFile: bundles[prop].path,
                files: bundles[prop].files,
                urlPrefix: config.urlPrefix + subPath,
                destPath: config.staticPath,
            }));
        }
    }
    return cssTasks;
}

function buildScripts(config) {
    var jsTasks = [],
        config = Merge(defaultConfig, config),
        assets = config.assets || {},
        bundles = assets.bundles;
    for(var prop in bundles) {
        if (bundles[prop].type == 'js') {
            var subPath = Path.dirname(bundles[prop].path);
            if (subPath) {
                subPath = '/' + subPath;
            }
            jsTasks = MergeStream(jsTasks, jsFlow({
                destFile: bundles[prop].path,
                files: bundles[prop].files,
                urlPrefix: config.urlPrefix + subPath,
                destPath: config.staticPath,
            }));
        }
    }
    return jsTasks;
}

function buildManifest(config) {
    var config = Merge(defaultConfig, config);
    Gulp.src([config.staticPath + '/*/*'], {base: Path.join(process.cwd(), config.staticPath)})
        .pipe(Rev())
        .pipe(Rev.manifest('manifest.json'))
        .pipe(Gulp.dest(config.buildPath));
}

function cleanAssets(config) {
    var config = Merge(defaultConfig, config);
    return Del([config.buildPath + '/*', config.staticPath + '/*']);
}

function watchAssets(config) {
    var config = Merge(defaultConfig, config),
        assets = config.assets || {},
        bundles = assets.bundles;
    for(var prop in bundles) {
        if (bundles[prop].type == 'css') {
            Gulp.watch(bundles[prop].files, config.styleTasks);
        }
    }
    for(var prop in bundles) {
        if (bundles[prop].type == 'js') {
            Gulp.watch(bundles[prop].files, config.scriptTasks);
        }
    }
}

function initTasks(config) {
    Gulp.task('styles', function () {
        return buildStyles(config);
    });
    Gulp.task('scripts', function () {
        return buildScripts(config);
    });

    Gulp.task('manifest', ['styles', 'scripts'], function () {
        return buildManifest(config);
    });
    Gulp.task('clean', function () {
        return cleanAssets(config);
    });
    Gulp.task('watch', function () {
        return watchAssets(config);
    });

    Gulp.task('assets', ['scripts', 'styles', 'manifest']);
    Gulp.task('default', ['assets']);
}

module.exports = {
    initTasks: initTasks,
    cssFlow: cssFlow,
    jsFlow: jsFlow,
    buildStyles: buildStyles,
    buildScripts: buildScripts,
    buildManifest: buildManifest,
    cleanAssets: cleanAssets,
    watchAssets: watchAssets,
}
