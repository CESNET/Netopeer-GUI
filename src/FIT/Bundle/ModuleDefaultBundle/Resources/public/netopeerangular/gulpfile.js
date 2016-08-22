var gulp = require('gulp')
		watch = require('gulp-watch')
		templateCache = require('gulp-angular-templatecache');

gulp.task('default', function () {
	return gulp.src('templates/**/*.html')
		.pipe(templateCache('templates.js', {
			standalone: true,
			module: 'configurationTemplates'
		}))
		.pipe(gulp.dest('public'));
});

gulp.task('watch', function () {
	gulp.watch('templates/**/*.html', ['default'])
		.on('change', function(evt) {
			console.log(
				'[watcher] File ' + evt.path + ' was ' + evt.type + ', compiling...'
			);
		});
});
