/**
 * Gruntfile.js
 *
 * Run 'grunt' in shell to compile javascript and css files
 *
 */
module.exports = function(grunt) {

  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    uglify: {
      options: {
          banner: '/*! <%= pkg.name %> <%= pkg.homepage %> */\n',
        mangle: false
      },
      dist: {
        files: {
          'web/app/js/elabftw.min.js': [
              'node_modules/jquery-jeditable/src/jquery.jeditable.js',
              'web/app/js/vendor/keymaster.js',
              'web/app/js/vendor/cornify.js',
              'web/app/js/vendor/jquery.rating.js',
              'web/app/js/vendor/3Dmol-nojquery.js',
              'web/app/js/src/3Dmol-helpers.js',
              'web/app/js/src/common.js'],

          'web/app/js/chemdoodle.min.js': [
              'web/app/js/vendor/chemdoodle/chemdoodle-unpacked.js',
              'web/app/js/vendor/chemdoodle/chemdoodle-uis-unpacked.js'],

          'web/app/js/fullcalendar.min.js': [
              'node_modules/fullcalendar/dist/fullcalendar.js',
              'node_modules/fullcalendar/dist/locale-all.js'],

          'web/app/js/team.min.js': 'web/app/js/src/team.js',

          'web/app/js/register.min.js': 'web/app/js/src/register.js',

          'web/app/js/close-warning.min.js': 'web/app/js/src/close-warning.js',
          'web/app/js/chemdoodle-canvas.min.js': 'web/app/js/src/chemdoodle-canvas.js',

          'web/app/js/dropzone.min.js': [
              'node_modules/dropzone/dist/dropzone.js'],

          'web/app/js/file-saver.min.js': 'node_modules/file-saver/src/FileSaver.js',
          'web/app/js/admin.min.js': 'web/app/js/src/admin.js',
          'web/app/js/editusers.min.js': 'web/app/js/src/editusers.js',
          'web/app/js/view.min.js': [
              'node_modules/@fancyapps/fancybox/dist/jquery.fancybox.js',
              'web/app/js/src/view.js'],
          'web/app/js/install.min.js': 'web/app/js/src/install.js',
          'web/app/js/tabs.min.js': 'web/app/js/src/tabs.js',
          'web/app/js/sysconfig.min.js': 'web/app/js/src/sysconfig.js',
          'web/app/js/todolist.min.js': 'web/app/js/src/todolist.js',
          'web/app/js/login.min.js': 'web/app/js/src/login.js',
          'web/app/js/change-pass.min.js': 'web/app/js/src/change-pass.js',
          'web/app/js/show.min.js': 'web/app/js/src/show.js',
          'web/app/js/edit.min.js': 'web/app/js/src/edit.js',
          'web/app/js/steps-links.min.js': 'web/app/js/src/steps-links.js',
          'web/app/js/relative-moment.min.js': 'web/app/js/src/relative-moment.js',
          'web/app/js/search.min.js': 'web/app/js/src/search.js',
          'web/app/js/tags.min.js': 'web/app/js/src/tags.js',
          'web/app/js/ucp.min.js': 'web/app/js/src/ucp.js',
          'web/app/js/profile.min.js': 'web/app/js/src/profile.js',
          'web/app/js/uploads.min.js': 'web/app/js/src/uploads.js',
          'web/app/js/doodle.min.js': 'web/app/js/src/doodle.js',
          'web/app/js/bootstrap-markdown.min.js': [
              'node_modules/marked/lib/marked.js',
              'node_modules/bootstrap-markdown-fa5/js/bootstrap-markdown.js',
              'node_modules/bootstrap-markdown-fa5/locale/*' ]
        }
      }
    },
    watch: {
      files: ['<%= uglify.files %>'],
      tasks: ['uglify']
    },
    cssmin: {
      target: {
        files: {
          'web/app/css/elabftw.min.css': [
              'web/app/css/tagcloud.css',
              'web/app/css/jquery.rating.css',
              'node_modules/prismjs/themes/prism.css',
              'node_modules/dropzone/dist/dropzone.css',
              'node_modules/fullcalendar/dist/fullcalendar.css',
              'node_modules/bootstrap/dist/css/bootstrap.css',
              'node_modules/@fancyapps/fancybox/dist/jquery.fancybox.css',
              'node_modules/jquery-ui-dist/jquery-ui.css',
              'web/app/css/autocomplete.css',
              'web/app/css/main.css'],

          'web/app/css/pdf.min.css': 'web/app/css/pdf.css',

          'web/app/css/bootstrap-markdown.min.css': 'node_modules/bootstrap-markdown/css/bootstrap-markdown.min.css',
          'web/app/css/tinymce/skin.min.css': 'node_modules/tinymce/skins/ui/oxide/skin.css',
          'web/app/css/tinymce/content.min.css': 'node_modules/tinymce/skins/ui/oxide/content.css',
          'web/app/css/tinymce/content.mobile.min.css': 'node_modules/tinymce/skins/ui/oxide/content.mobile.css',
          'web/app/css/tinymce/skin.mobile.min.css': 'node_modules/tinymce/skins/ui/oxide/skin.mobile.css',
        }
      }
    },
    shell: {
      // run yarn install
      yarninstall: {
        command: 'yarn install'
      },
      tinymce: {
        // copy the mobile font file
        command: 'cp node_modules/tinymce/skins/ui/oxide/fonts/tinymce-mobile.woff web/app/css/tinymce/fonts/tinymce-mobile.woff'
      }
    }
  });

  grunt.loadNpmTasks('grunt-contrib-uglify-es');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-contrib-cssmin');
  grunt.loadNpmTasks('grunt-shell');

  // before minifying js it is preferable to do 'yarn install' to update the dependencies
  grunt.registerTask('yarn', 'shell:yarninstall');
  grunt.registerTask('default', ['yarn', 'uglify', 'cssmin', 'tinymce']);
  grunt.registerTask('css', 'cssmin');
  grunt.registerTask('tinymce', 'shell:tinymce');
};
