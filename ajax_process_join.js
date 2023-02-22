



	//-----------------------------------------
	// process member join
	//-----------------------------------------
	$("#member_join_form_submit").on("click", function(){
		// get inputs
		var forename = $("#member_join_form_forename").val();
		var surname = $("#member_join_form_surname").val();
		var email = $("#member_join_form_email").val();
		var password_1 = $("#member_join_form_password_1").val();
		var password_2 = $("#member_join_form_password_2").val();
		var member_join_password_strength_score = $("#member_join_password_strength_score").val();

		// reset all errors, then do basic checks (PHP will do a complete check)
		$("#member_join_form_forename,#member_join_form_surname, #member_join_form_email,#member_join_form_password_1,#member_join_form_password_2").css("outline","0px solid white");


		$("#member_join_form_submit").prop('disabled', true);
		var errorCount =0;
		var errorText ='<p>';

		if (forename.length <2) {
			errorCount++;
			errorText+= '<p>The First name is too short or missing.';
			$("#member_join_form_forename").css("outline","1px solid red");
		}


		if (surname.length <2) {
			errorCount++;
			errorText+= '<p>The Surname  is too short or missing.';
			$("#member_join_form_surname").css("outline","1px solid red");
		}


		if (email.length <6) {
			errorCount++;
			errorText+= '<p>The Email address is too short or missing.';
			$("#member_join_form_email").css("outline","1px solid red");
		}

		if (password_1.length <8) {
			errorCount++;
			errorText+= '<p>The Password is too short or missing (min 8 chars - see help link for more details).';
			$("#member_join_form_password_1, #member_join_form_password_2").css("outline","1px solid red");
		}



		if (password_1 != password_2) {
			errorCount++;
			errorText+= '<p>The Passwords do not match.';
			$("#member_join_form_password_1, #member_join_form_password_2").css("outline","1px solid red");
		}




		if (errorCount == 0) {
			var memberjoin_ajax = $.ajax({
				url: "/members/ajax_process_join.php",
				data: {forename: forename, surname: surname, user_email: email, password_1: password_1, password_2: password_2, member_join_password_strength_score:member_join_password_strength_score},
				type: "POST",
				cache: false,
				dataType: "json" // expect a json response
			});

			// success from http point of view, but still can be errors in the PHP response
			 memberjoin_ajax.done(function (data, textStatus, jqXHR) {
				if (data.STATUS =='SUCCESS') {// Application success text from PHP
					$("#member_join_form_wrapper").html(data.ResponseText); //success message replaces entire page

					// $("#member_join_form_wrapper").animate({ scrollTop: 0 }, 200);
					//https://stackoverflow.com/questions/19012495/smooth-scroll-to-div-id-jquery
					$('html, body').animate({
					        scrollTop: $("#member_join_form_wrapper").offset().top -100
					    }, 1000);

				} else {// response is an object, but not SUCCESS
					$("#member_join_form_errors_modal").modal("show"); // open errors div and display error text from PHP
					$("#member_join_form_errors_body").html(data.ResponseText);
					$("#member_join_form_submit").prop('disabled', false);
					if (whatIsIt(data.BADFIELDS) == "Array") {
						var arrayLength = data.BADFIELDS.length;

						for (var i = 0; i < arrayLength; i++) {
						    var badfield= data.BADFIELDS[i];
						    $(badfield).css("outline","1px solid blue");;
						}
					}
				}

			}); // END ajax done



			// ajax failed
			memberjoin_ajax.fail(function(jqXHR, textStatus, errorThrown ) {
				$("#member_join_form_errors_modal").modal("show");  // open errors div and display error text from PHP
				$("#member_join_form_errors_body").html("Ajax error: (.fail) " + errorThrown + "\n\nThis may be a temporary problem, please try again.\n\nIf it still fails, please check other web sites to test your Internet connection.");
				$("#member_join_form_submit").prop('disabled', false);
				});

		} // if (errorCount ==0) TRUE
		else {
			$("#member_join_form_errors_modal").modal("show");  // open errors div and display error text from PHP
			$("#member_join_form_errors_body").html(errorText);
			$("#member_join_form_submit").prop('disabled', false);
		} // if (errorCount ==0) FALSE
	}) // END $("#member_join_form_submit").on("click", function()













function password_strength_indicator(minLen, thePassword) {

		var score =0;

		switch (true) {
		    case (thePassword.length < minLen):
		    	score -= 10;
		        break;
		    case (thePassword.length < 11):
		        break;
		    case (thePassword.length < 12):
		    		score += 1;
		        break;
		    case (thePassword.length < 14):
		    		score += 2;
		        break;
		    case (thePassword.length < 20):
		    		score += 3;
		        break;
		    default:
		    		score += 4;
		        break;
		}


	if (thePassword.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/))  {
		score += 1;
		// console.log("Upper & Lower case");
	}


	if (thePassword.match(/([a-z].*[A-Z].*[a-z])/)  )  {
		score += 1;
		// console.log("Upper  case - not at start/end");
	}


	if (thePassword.match(/([A-Z].*[a-z].*[A-Z])/) )  {
		score += 1;
		// console.log("Lower  case - not at start/end");
	}


	if (thePassword.match(/([a-zA-Z])/) && thePassword.match(/([0-9])/))   {
		score += 1;
		// console.log("Letter & number");
	}

	if (thePassword.match(/([-¬`!"£\$%\^&\*\(\)_=\+\[\]\{\}:; @'~#,<\.>/\?\|\\ ])/))   {
		score += 2;
		// console.log("Special char KL");
	}

	if (thePassword.match(/(.*[-¬`!"£\$%\^&\*\(\)_=\+\[\]\{\}:; @'~#,<\.>/\?\|\\ ].*[-¬`!"£\$%\^&\*\(\)_=\+\[\]\{\}:; @'~#,<\.>/\?\|\\ ])/))  {
		score += 2;
		// console.log("2 x Special cahr");
	}


	// check for user entering common sequences
	var forbiddenSequences = "0123456789";
	forbiddenSequences +=  "abcdefghijklmnopqrstuvwxyz";
	forbiddenSequences +="qwertyuiop";
	forbiddenSequences += "asdfghjkl";
	forbiddenSequences += "zxcvbnm";
	forbiddenSequences +="!@#$%^&*()_+";
	forbiddenSequences +='!"£$%^&*()_+';
	forbiddenSequences +="password";
	forbiddenSequences +="09876543210";

	var needle = "";

	if (thePassword.length > 4) {
		for (j = 0; j < (thePassword.length - 3); j += 1) { // iterate the word trough a sliding window of size 4:
        	needle = thePassword.toLowerCase().substring(j, j + 4);
        	// console.log("needle " + needle);

        	if (forbiddenSequences.indexOf(needle) > 0) {
        		score -= 4;
        		// console.log("Part of password is forbidden string " + needle);
        		break;
        	}
        }
	}


	// check for uniqness of characters , eg no asasas or ddddd
	if (thePassword.length > 4) {

		var unique_characters_in_pass = unique_char(thePassword);
		var unique_ratio =  Math.round((unique_characters_in_pass  / thePassword.length)*100); // score of 100 means no repeated chars, more repeats less the score

		// console.log("unique_characters_in_pass " + unique_characters_in_pass);
		// console.log("unique_ratio " + unique_ratio);



		switch (true) {
		    case (unique_ratio > 90):
		    	score += 4;
		        break;

		    case (unique_ratio > 75):
		    	score += 2;
		        break;

		    case (unique_ratio > 60):
		        break;

		    case (unique_ratio > 50):
		    	score -= 1;
		        break;

		    case (unique_ratio > 40):
		    	score -= 2;
		        break;

		    default:
	    		score -= 3;
		        break;
		}
	}


	$("#member_join_password_strength_score").val(score);
	strength_bar(score,thePassword.length);
}




function unique_char(str) {
 var uniql="";
 for (var x=0;x < str.length;x++) {

	 if(uniql.indexOf(str.charAt(x))==-1) {
	  	uniql += str[x];
	  }
 }
return uniql.length;
}















function strength_bar(score, pwLength) {

	var width_percentage =0;
	switch (true) {
	    case (score < 4):
	    	$(".choose_password_strength_bar").removeClass("bg-success");
	    	$(".choose_password_strength_bar").removeClass("bg-warning");
	    	$(".choose_password_strength_bar").addClass("bg-danger");
	        break;

	    case (score < 7):
	    	$(".choose_password_strength_bar").removeClass("bg-success");
	    	$(".choose_password_strength_bar").removeClass("bg-danger");
	    	$(".choose_password_strength_bar").addClass("bg-warning");
	        break;

	    default:
	    	$(".choose_password_strength_bar").removeClass("bg-warning");
	    	$(".choose_password_strength_bar").removeClass("bg-danger");
	    	$(".choose_password_strength_bar").addClass("bg-success");
	        break;
	}


	width_percentage = score*10;;
	if (score < 1) { width_percentage =10; } // stay within 0 - 100
	if (score > 9) { width_percentage =100; }
	if (pwLength == 0) { width_percentage =0; }

	$(".choose_password_strength_bar").css("width", width_percentage + '%');
	$('.choose_password_strength_bar').attr('aria-valuenow', width_percentage);
	// $(".choose_password_strength_bar").html( score + " / " + width_percentage);
}





