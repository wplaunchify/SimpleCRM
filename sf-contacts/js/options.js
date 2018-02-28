jQuery( document ).ready(function() {
	jQuery('#sf_create_post .required').on('keyup', function(){
		if( jQuery(this).val().length > 0 ){
			jQuery(this).parents('li').removeClass('error');
		}
	});

   	jQuery('#sf_create_post .submit').on('click', function(e){
		e.preventDefault();

		var Errors = 0,
			Form = jQuery('#sf_create_post'),
			Required = Form.find('.required');
		
		Required.each(function() {
		    if( !jQuery(this).val() ) {
		        jQuery(this).parents('li').addClass('error');
		        Errors++;
		    }
		});

		if( Errors == 0) {
			Form.find('.submit').prop('disabled', true);

			jQuery.ajax({
				url: ajax_url.url,
				data: { action: 'sf_process_form', form: Form.serialize() },
				type: 'POST',
				success: function (result) {
					var result = JSON.parse( result );

					Form.find('.submit').prop('disabled', false);

					if( result.done ) {
						Form.find('.message').text( result.message );
						Form[0].reset();
					} else {
						Form.find('.message').text( result.message );

					}

					setTimeout(function(){ 
						Form.find('.message').text('');
					}, 3000);
				},
				error: function (xhr, status, error) {
					console.log(xhr,status,error);
				}
			});	
		}
	});
});
