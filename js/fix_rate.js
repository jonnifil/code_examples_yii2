/**
 * Created by jonni on 25.04.18.
 */
var grid;
$(document).ready(function () {
    grid = new GridFixRate();
});

var GridFixRate = function () {
    this.block = $('#grid');
    this.id_list = [];
    this.edit_dialog = new EditDialog(this);
};

GridFixRate.prototype = {
    constructor: GridFixRate,

    change_checked: function () {
        var keys = $('#grid').yiiGridView('getSelectedRows');
        return keys.length;
    },

    edit: function () {
        var keys = $('#grid').yiiGridView('getSelectedRows');
        this.id_list = keys;
        this.edit_dialog.show();
    },

    delete_checked: function () {
        var keys = $('#grid').yiiGridView('getSelectedRows');
        var conf = confirm('Вы действительно желаете удалить ' + keys.length + ' строк?');
        if (conf) {
            var current_href = window.location.toString();
            var active_block = $('div.active', this.block);
            if (active_block != undefined) {
                $.ajax({
                    url: "/fix-rate/delete-rows",
                    data: {
                        ids: keys
                    },
                    method: "POST",
                    error: function () {
                        alert('Произошла ошибка удаления. Повторите попытку позже.');
                    },
                    success: function (resp) {
                        window.location.href = current_href;
                    }
                });
            }
        }
    }
};

var EditDialog = function (parent) {
    this.parent = parent;
    this.block = $('#edit_dialog');
    this.error_block = $('#error_block');
    $('[name="field"]', this.block).on('change', $.proxy(this.show_field, this));
    $('[name="save"]', this.block).on('click', $.proxy(this.save, this));
    $('.collected', this.block).on('keyup', $.proxy(this.hide_danger, this));
    $('input[name="price"]', this.block).on('keypress', $.proxy(this.check_price, this));
};

EditDialog.prototype = {
    constructor: EditDialog,

    show: function () {
        $('input[name="field"]', this.block).removeAttr('checked');
        $('input.collected', this.block).val('');
        this.hide_danger();
        this.show_field();
        this.block.modal('show');
    },

    show_field: function () {
        $('div.field_data', this.block).addClass('hide').removeClass('active');
        var checked_val = $('input[name="field"]:checked', this.block).val();
        if (checked_val != undefined) {
            $('div[name="field_' + checked_val + '"]', this.block).removeClass('hide').addClass('active');
            $('button[name="save"]', this.block).removeAttr('disabled')
        } else {
            $('button[name="save"]', this.block).attr('disabled', true);
        }
    },

    save: function () {
        var current_href = window.location.toString();
        var active_block = $('div.active', this.block);
        if (active_block != undefined) {
            let field = $('.collected', active_block);
            let name = field.attr('name');
            let value = field.val();
            let field_name = active_block.find('label.control-label').text();
            if (value.trim() == ''){
                $('span[name="danger_field"]', this.block).html(field_name);
                this.error_block.removeClass('hide');
                return false;
            }
            let conf = confirm('Вы действительно желаете изменить поле '+ field_name +' в '+ this.parent.id_list.length +' строках?');
            if (conf) {
                $.ajax({
                    url: "/fix-rate/edit-rows",
                    data: {
                        name: name,
                        value: value,
                        ids: this.parent.id_list
                    },
                    method: "POST",
                    error: function () {
                        alert('Произошла ошибка сохранения данных. Повторите попытку позже.');
                    },
                    success: function (resp) {
                        window.location.href = current_href;
                    }
                });
            }

        }
        this.block.modal('hide');
    },

    hide_danger: function () {
        this.error_block.addClass('hide');
    },

    check_price: function(e) {
        e = e || event;

        if (e.ctrlKey || e.altKey || e.metaKey) return;

        var chr = getChar(e);

        if (chr == null) return;

        if (chr < '0' || chr > '9') {
            return false;
        }
    }
};
// event.type должен быть keypress
function getChar(event) {
    if (event.which == null) { // IE
        if (event.keyCode < 32) return null; // спец. символ
        return String.fromCharCode(event.keyCode)
    }

    if (event.which != 0 && event.charCode != 0) { // все кроме IE
        if (event.which < 32) return null; // спец. символ
        return String.fromCharCode(event.which); // остальные
    }

    return null; // спец. символ
}
