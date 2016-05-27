module.exports = function(grunt) {
    grunt.initConfig({
        uglify: {
            app: {
                files: {
                    'public/app.min.js': [
                        'client/assets/components/list.js/dist/list.min.js',
                        'client/assets/js/app.js'
                    ]
                }
            }
        },
        less: {
            app: {
                files: {
                    'public/app.min.css': 'client/assets/less/app.less'
                }
            }
        },
        copy: {
            fonts: {
                files: [{
                    expand: true,
                    cwd: 'client/assets/components/font-awesome/fonts',
                    src: '**',
                    dest: 'public/fonts'
                }]
            },
            images: {
                files: [{
                    expand: true,
                    cwd: 'client/assets/images',
                    src: '**',
                    dest: 'public/images'
                }]
            }
        },
        watch: {
            scripts: {
                files: ['client/assets/js/*.js'],
                tasks: ['uglify'],
            },
            styles: {
                files: ['client/assets/less/*.less'],
                tasks: ['less'],
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-less');
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-watch');

    grunt.registerTask('default', ['uglify', 'less', 'copy']);
};
