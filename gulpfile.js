var gulp = require('gulp');
var templateCache = require('gulp-angular-templatecache');

gulp.task('default', function () {
	return gulp.src('templates/**/*.html')
		.pipe(templateCache('templates.js', {
			standalone: true,
			module: 'configurationTemplates'
		}))
		.pipe(gulp.dest('public'));
});