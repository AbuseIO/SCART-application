var scrollTopStep = 250;
var scrollTopTrigger = 1000;

$(document).ready(function() {

    $("#idImageScrollArea").height($('#layout-body').height() - $('#idInputArea').height() - 5);
    $("#idImageScrollArea").scrollTop(scrollTop);
    $("#idImageScrollArea").scroll(function (evt) {

        var top = $("#idImageScrollArea").scrollTop();
        if (top > scrollTopTrigger) {
            $.request('tiles::onScrollNext',{
                update: { show_result: '#show_result' },
                data: { lastLoading: lastLoading }
            });
            scrollTopTrigger += scrollTopStep;
        }
    });
});
