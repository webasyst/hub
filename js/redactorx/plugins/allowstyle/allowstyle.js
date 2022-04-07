RedactorX.add('plugin', 'allowstyle', {
  init: function() {
    const origFunc = this.app.content.removeTagsWithContent;

    this.app.content.removeTagsWithContent = function(html, tags) {
      tags = ['script'];
      return origFunc.bind(this)(...arguments);
    }
  }
});
