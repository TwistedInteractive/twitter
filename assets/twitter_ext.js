jQuery(function(){
	var $ = jQuery;
    
    //Publish list
    $(".twitter_ext").each(function(){
        var self = this;

        $('.twitter_ext_button',self).click(function(){
            $('.twitter_ext_tweetbox',self).css('display','block');
        });

        $('.twitter_ext_cancel',self).click(function(){
            $('.twitter_ext_tweetbox',self).css('display','none');
        });
    });

    //Edit
    $('.twitter_ext_send').click(function(){
        var e_id = $(this).attr("e_id");
        var f_id = $(this).attr("f_id");
        var s_id = $(this).attr("s_id");
        var action = $(this).attr("act");

        switch(action){
            case "tweet":
                $.get("/symphony/extension/twitter/autotweet/", { action:action, entry_id: e_id, field_id: f_id },
                             function(data){
                                 console.log(data);
                                 $(self).removeClass('tweeting');
                                 $(self).addClass('tweeted');
                             });
            break;
            
            case "delete":
                $.get("/symphony/extension/twitter/autotweet/", { action:action, statusid: s_id, entry_id: e_id, field_id: f_id},
                             function(data){
                                 console.log(data);
                                 $(self).removeClass('tweeting');
                                 $(self).addClass('tweeted');
                             });
            break;
        }
        var self=this;
        if($(this).hasClass('tweeting') || $(this).hasClass('tweeted')) return false;

        $(this).addClass('tweeting');


        return false;
    });
});
