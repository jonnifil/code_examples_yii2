/**
 * Created by jonni on 29.08.16.
 */
var test_card;
$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip();
    test_card = new TestCard();
    test_card.init();
});

var QuestionType = {
    text: '1',
    numeric: '2',
    select: '3',
    multiselect: '4',
    yes_no: '5'
};

var TestCard = function() {
    var $this = this;
    this.block = $('#main_info');
    this.data = null;
    this.test_dialog = new TestDialog(this);
    $('[name="start_test"]', this.block).on('click', $.proxy(this.start_test, this));

};

TestCard.prototype = {
    constructor: TestCard,
    init: function () {
        var params = parse_hash();
        if(params.id != undefined)
            this.id = params.id;
        else return false;
        ecm_ajax({
            method: 'checklist',
            args: {checklist_id:this.id},
            callback: $.proxy(this.show, this)
        });

    },

    show: function(data){
        this.data = data;
        this.load_main();
    },

    load_main: function(){
        var order = this.data;
        for (var attr in order){
            var value = order[attr];
            $('[name="' + attr + '"]', this.block).html(value == null ? '' : value);
            if (attr == 'descr'){
                $('[name="' + attr + '"].title').html(value == null ? 'Пройти тест' : value);
            }
        }
    },

    start_test: function(){
        var $this = this;
        show_confirm('Вы действительно хотите начать тестирование?', function(){
            $this.test_dialog.init();
           // $this.test_dialog.timer();
        });
    }

};

var TestDialog = function(parent){
    this.card = parent;
    this.test = {};
    this.question_list = [];
    this.answer_list = [];
    this.current_num = null;
    this.interval = null;
    this.block = $('#test_progress');
    this.answer_block = $('[name="answer_block"]', this.block);
    this.user_answer_count = $('[name="answer_count"]', this.block);
    $('[name="save_answer"]', this.block).on('click', $.proxy(this.save_answer, this));
    $('[name="next"]', this.block).on('click', $.proxy(this.next, this));
    $('[name="save_test"]', this.block).on('click', $.proxy(this.save_test, this));
};

TestDialog.prototype = {
    constructor: TestDialog,
    init: function () {
        var checklist_id = this.card.data.id;
        ecm_ajax({
            method: 'get_test',
            args: {checklist_id:checklist_id},
            control: $('[name="start_test"]', this.card.block),
            callback: $.proxy(this._op_result, this)
        });
    },

    _op_result: function(resp){
        if(resp.test != undefined){
            this.test = resp.test;
            this.load_main();
        }

        if(resp.question_list != undefined){
            this.question_list = resp.question_list;
            this.load_answer_list();
            this.show_progress_list(this.question_list.length);
            this.show_question(0);
            this.timer();
        }

        if(resp.save_result != undefined){
            wait.hide();
            if(resp.save_result == true){
                this.block.addClass('hide');
                this.check_test_result();
            }else
                show_alert('Тестовый сервер не доступен. Не закрывайте окно и попробуйте отправить результат через несколько минут.')
        }

        if(resp.save_check_result != undefined){
            document.location.href = '/eml_test/show#id=' + this.test.id;
        }
    },

    check_test_result: function(){
        ecm_ajax({
            method: 'check_test_result',
            args: {
                test_id: this.test.id
            },
            callback: $.proxy(this._op_result, this)
        });

    },

    timer: function(){
        var test_time = this.card.data.test_duration+':00',
            $this = this;
        $('#timer').attr('long', test_time).html(test_time);
        if(is_importer == false)
        this.interval = setInterval (function ()
        {
            function f (x) {return (x / 100).toFixed (2).substr (2)}
            var o = document.getElementById ('timer'), w = 60, y = o.innerHTML.split (':'),
                v = y [0] * w + (y [1] - 1), s = v % w, m = (v - s) / w;
            if (s < 0)
                var v = o.getAttribute ('long').split (':'), m = v [0], s = v [1];
            o.innerHTML = [f (m), f (s)].join (':');
            if(m == 0 && s == 0)
                $this.save_test();
        }, 1000);
    },

    load_main: function(){
        $('[name="question_count"]', this.block).html(this.card.data.count_questions);
        this.user_answer_count.html('0');
        $('[name="start_test"]',this.card.block).remove();
        this.block.removeClass('hide');
        if(is_importer == true){
            $('[name="save_test"]', this.block).addClass('hide');
            $('[name="save_answer"]', this.block).addClass('hide');

        }
        else{
            $('[name="save_test"]', this.block).removeClass('hide');
            $('[name="save_answer"]', this.block).removeClass('hide');
        }
    },

    show_progress_list: function(count){
        var list = $('[name="answer_progress_list"]', this.block),
            $this = this,
            num;
        for(var i=0; i < count; i++){
            num = i+1;
            var li = $('<li class="checkbox"><i class="fa fa-fw fa-square-o"></i>'+num+' Вопрос</li>')
                .attr('name', i)
                .on('click', function(){
                    $this.load_question(this);
                });
            li.appendTo(list);
        }
    },

    load_question: function(element){
        var num = parseInt($(element).attr('name'));
        this.show_question(num);
    },

    show_question: function(num){
        if(this.current_num == num)
            return false;
        $('[name="answer_progress_list"]', this.block).find('li').removeClass('bg-primary');
        $('[name="'+num+'"]',$('[name="answer_progress_list"]', this.block)).addClass('bg-primary');
        this.current_num = num;
        $('[name="current_number"]', this.block).html(num+1);
        var question = this.question_list[num],
            answer_ids = this.answer_list[this.current_num].answer_ids,
            text_answer, answer, input;
        $('[name="specification"]', this.block).html(question.specification);
        this.answer_block.empty();
        switch (question.answer_type_id){
            case QuestionType.select :
                for(var i in question.answer_list){
                    answer = question.answer_list[i];
                    input = $('<input>').attr('type', 'radio').attr('name', 'answer_id').val(answer.id);
                    if(answer_ids.indexOf(answer.id) != -1)
                        $(input).attr('checked', true);
                    text_answer = $('<label>');
                    text_answer.append(input).append(answer.descr);
                    $('<li class="radio"></li>')
                        .append(text_answer)
                        .appendTo(this.answer_block)
                }
                break;
            case QuestionType.multiselect :
                for(var i in question.answer_list){
                    answer = question.answer_list[i];
                    input = $('<input>').attr('type', 'checkbox').attr('name', 'answer_id').val(answer.id);
                    if(answer_ids.indexOf(answer.id) != -1)
                        $(input).attr('checked', true);
                    text_answer = $('<label>');
                    text_answer.append(input).append(answer.descr);
                    $('<li class="checkbox"></li>')
                        .append(text_answer)
                        .appendTo(this.answer_block)
                }
                break;
            default:
                console.log('answer default')
        }
        this.current_time_duration();
    },

    load_answer_list: function(){
        var question;
        for(var i in this.question_list){
            question = this.question_list[i];
            this.answer_list[i] = {
                question_id: question.id,
                answer_ids: [],
                time_duration: 0
            }
        }
    },

    save_answer: function(){
        var $this = this,
            ansver_ids = [];
        $('input:checked', this.answer_block).each(function(){
            var ansver_id = $(this).val();
            ansver_ids.push(ansver_id);
        });
        if(ansver_ids.length == 0){
            show_alert('Укажите ответ');
            return false;
        }
        this.answer_list[this.current_num].answer_ids = ansver_ids;
        var user_answer_count = this.calculate_user_answer();
        if(user_answer_count < this.card.data.count_questions)
            this.next();
    },

    calculate_user_answer: function(){
        var user_answer_count = 0;
        for(var i in this.answer_list){
            if(this.answer_list[i].answer_ids.length > 0) {
                user_answer_count++;
                $('[name="'+i+'"]', $('[name="answer_progress_list"]', this.block))
                    .find('i')
                    .removeClass('fa-square-o')
                    .addClass('fa-check-square-o');
            }else{
                $('[name="'+i+'"]', $('[name="answer_progress_list"]', this.block))
                    .find('i')
                    .removeClass('fa-check-square-o')
                    .addClass('fa-square-o');
            }
        }
        this.user_answer_count.html(user_answer_count);
        return user_answer_count;
    },

    current_time_duration: function(){
        var $this = this;
        setInterval (function (){
            $this.answer_list[$this.current_num].time_duration++;
        }, 1000);
    },

    next: function(){
        var $this = this;
        var count_questions = this.card.data.count_questions,
            next_num = this.current_num + 1;
        for(var i=0; i < count_questions; i++){
            next_num = next_num + i >= count_questions ? 0 : next_num + i;
            if($this.answer_list[next_num].answer_ids.length == 0){
                $this.show_question(next_num);
                break;
            }
        }
    },

    save_test: function(){
        wait.show();
        clearInterval(this.interval);
        ecm_ajax({
            method: 'save_test',
            args: {
                test_id: this.test.id,
                answer_list: this.answer_list
            },
            callback: $.proxy(this._op_result, this)
        });
    }
};