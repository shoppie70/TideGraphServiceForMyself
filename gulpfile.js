const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const tailwindcss = require('tailwindcss');
const postcss = require('gulp-postcss');

gulp.task('styles', function () {
    return gulp
        .src('assets/**/*.scss')
        .pipe(sass().on('error', sass.logError))
        .pipe(postcss([tailwindcss]))
        .pipe(gulp.dest('assets/'));
});

// watchタスクを定義
gulp.task('watch', function () {
    gulp.watch('assets/**/*.scss', gulp.series('styles'));
});

// defaultタスクを定義
gulp.task('default', gulp.series('styles', 'watch'));