/**
 * Created by jonni on 04.05.18.
 */
$(document).ready(function () {
    if (isset_news != undefined && isset_news == 1) {
        var news_dialog = new NewsDialog();
        news_dialog.show();
    }
});

var NewsDialog = function () {
    this.block = $('#modal_news');
    this.path = window.location.pathname.substring(1,7) == 'broker' ? '/broker/user/' : '/carrier/user/';
    $('button[name="save"]', this.block).on('click', $.proxy(this.save_confirm, this));
};

NewsDialog.prototype = {
    constructor: NewsDialog,

    show: function () {
        var news_id = $('input[name="news_id"]', this.block).val();
        $.ajax({
            url: this.path + 'newsviewed',
            dataType: 'json',
            method: 'POST',
            data: {news_id: news_id},
        });
        this.block.modal('show');
    },

    save_confirm: function () {
        var news_id = $('input[name="news_id"]', this.block).val();
        $.ajax({
            url: this.path + 'newsshowed',
            dataType: 'json',
            method: 'POST',
            data: {news_id: news_id},
        });
        this.block.modal('hide');
    }
};
