<? 




function qty_controls($cart_line_id, $session_int_id, $current_qty)
	{ // javascript controls

	$controls = "<a  href='#' onclick=\"cart_qty_adjust($cart_line_id, $session_int_id, 'ADD');return false;\"><i class='fa fa-lg fa-plus-square' aria-hidden='true'></i></a> \n";

	if ($current_qty == 1) // use version of cart_qty_adjust() with a confirm dialogue if deleting
		$controls .= "<a  href='#' onclick=\"cart_qty_adjust_confirm($cart_line_id, $session_int_id, 'SUB');return false;\"><i class='fa fa-lg fa-minus-square' aria-hidden='true'></i></a> \n";
	else
		$controls .= "<a  href='#' onclick=\"cart_qty_adjust($cart_line_id, $session_int_id, 'SUB');return false;\"><i class='fa fa-lg fa-minus-square' aria-hidden='true'></i></a> \n";

	$controls .= "<a  href='#' onclick=\"cart_qty_adjust_confirm($cart_line_id, $session_int_id, 'DEL');return false;\"><i class='fa fa-lg fa-trash' aria-hidden='true'></i></a> \n";


	return  $controls;
	//return "Qty";
	}







function display_cart_contents($calling_script_session_int_id)
	{
	global $carts_table, $products_table, $price_table, $in_cart_options_table, $option_types_table;
	global $options_price_table, $option_value_pool_table, $shipping_rates_table, $quantity_discounts_table, $cart_scripts_url ;


	$out = array();
	$out['VIEWCART'] = "";
	$out['CHECKOUT'] = "";
	$out['EMAIL'] = "";
	$out['HASPRODUCTS'] = false;
	$out['SHIPPING'] = 0;
	$out['GRANDTOTAL'] = 0;
	$out['WEIGHT'] = 0;


	$session_info = cart_session();
	$currency_id = $session_info['CURRENCY_ID'];
	$currency_symbol = $session_info['CURRENCY_SYMBOL'];
	$session_int_id = $session_info['SESSION_INT_ID'];
	$country_name = $session_info['COUNTRY_NAME'];
	$fx_rate = $session_info['FX_RATE'];
	$rounding  = $session_info['ROUNDING'];
	$ship_zone  = $session_info['SHIP_ZONE'];

	if ($calling_script_session_int_id != $session_int_id) 
		{
		$out['VIEWCART'] .= "Cannot find your cart";
		$out['CHECKOUT'] .= "Cannot find your cart";
		return $out;
		}

	
	// create query to display cart contents - need to do left joins on the option tables as most of 
	// the options will be, ... optional! i.e. may not be present.
	// also, for 'PC' pricing, since some options are free and other have a cost, allow NULL rows on 
	// the options_price table in the currency where clause

	// add in an extra row after results with a high cart_line_id so it will be sorted last - this 
	// will simplify the PHP processing

	if (CURRENCY_CONVERSION_METHOD == "FX") //single price converted to other currencies by a FX rate
		{ 
		$view_cart_query = "select $carts_table.cart_line_id, product_code, PROD.product_id,  quantity, 
				PROD.base_price as prod_price, weight, customer_title,
				ICO.option_type_id, ICO.option_id as incart_option_id, 
				option_name, extra_cost, OVP.value as ticked_value, option_type,
				OVP.base_price as opt_price, 
				ICO.value as typed_value, option_display_order
				from $carts_table join $products_table as PROD on $carts_table.product_id =PROD.product_id
				left join $in_cart_options_table as ICO using(cart_line_id)
				left join $option_types_table on ICO.option_type_id=$option_types_table.option_type_id
				left join $option_value_pool_table as OVP on ICO.option_id=OVP.option_id
				where $carts_table.session_int_id=:session_int_id
				union
				select 3000000000 ,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL
				order by cart_line_id, option_display_order";
		
		$view_cart_parameters = array(
			array('P' => ':session_int_id', 'V'=> $session_int_id, 'T' => 'I')
			);		
		}


	if (CURRENCY_CONVERSION_METHOD == "PC") // distinct price per currency 
		{ 
		// use a sub select for this instead????
		$view_cart_query = "(select $carts_table.cart_line_id, product_code, PROD.product_id, quantity, 
			$price_table.price as prod_price, weight, customer_title,
			ICO.option_type_id, ICO.option_id as incart_option_id, 
			option_name, extra_cost, OVP.value as ticked_value, option_type,
			$options_price_table.price as opt_price, 
			ICO.value as typed_value, option_display_order
			from $carts_table 
			join $products_table as PROD on $carts_table.product_id =PROD.product_id
			join $price_table on PROD.product_id=$price_table.product_id
			left join $in_cart_options_table as ICO using(cart_line_id)
			left join $option_types_table on ICO.option_type_id=$option_types_table.option_type_id
			left join $option_value_pool_table as OVP on ICO.option_id=OVP.option_id
			left join $options_price_table on OVP.option_id=$options_price_table.option_id
			where $carts_table.session_int_id=:session_int_id and $price_table.currency_id=:currency_id_a 
			and ($options_price_table.currency_id=:currency_id_b  OR $options_price_table.currency_id IS NULL))
			union
			(select 3000000000 ,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
			order by cart_line_id, option_display_order";
		
		$view_cart_parameters = array(
			array('P' => ':currency_id_a', 'V'=> $currency_id, 'T' => 'I'),
			array('P' => ':currency_id_b', 'V'=> $currency_id, 'T' => 'I'),
			array('P' => ':session_int_id', 'V'=> $session_int_id, 'T' => 'I')
			);		
		}

	$cart_contents = db_single_prepared_query($view_cart_query, $view_cart_parameters);

	$cart_rows = $cart_contents['ROWCOUNT'];

	if ($cart_rows == 1) // the dummy extra row
		{ 
		$out['VIEWCART'] .= "Cart is empty";
		$out['CHECKOUT'] .= "Cart is empty";
		}
	else
		{
		//$sumary = cart_summary($session);
		$change_country_link = "$cart_scripts_url/change_country.php?action=CARTFORM";

		// get percentage discounts (based on qty ordered) for products in this cart - discount applies regardless of CURRENCY_CONVERSION_METHOD
		// only products with discounts (or have sufficient qty in cart) are returned by this query
		$discounts_query = "select cart_line_id, max(percent_off) as discount_percantage, apply_to_options 
			from  $carts_table join  $quantity_discounts_table using(product_id) 
			where session_int_id = :session_int_id and quantity>=atleast_ordered
			group by cart_line_id, apply_to_options";
		
		$discounts_parameters = array(
			array('P' => ':session_int_id', 'V'=> $session_int_id, 'T' => 'I')
			);		

		$discounts = db_single_prepared_query($discounts_query, $discounts_parameters);

		$num_discounts = $discounts['ROWCOUNT'];
		
		$discounted_products_array = array();
		if ($num_discounts > 0) 
			{
			for ($i=0;$i<$num_discounts;$i++) 
				{
				$cart_line_id = $discounts['ROWS'][$i]['cart_line_id'];
				$discount_percantage = $discounts['ROWS'][$i]['discount_percantage'];
				$apply_to_options = $discounts['ROWS'][$i]['apply_to_options'];				
				$discounted_products_array[$cart_line_id]['DISCOUNT'] = $discount_percantage;
				$discounted_products_array[$cart_line_id]['OPTIONS'] = $apply_to_options;
				}
			}


		
		$total_price = 0;
		$total_weight = 0;
		$this_cart_line_id = 0;
		$options_price = 0;
		$line_price = 0;
		$option_desc="";

		$tableopen = "\n\n\n\n\n\n<div id='cart_grid' class='container-fluid'>\n";
		$out['VIEWCART'] .= $tableopen;
		$out['CHECKOUT'] .= $tableopen;
		$out['EMAIL'] .= "<center><table width='100%' cellpadding='5' cellspacing='0'>\n";;

		// qty controls only for view cart, not checkout
		//$out['VIEWCART'] .= "<div class='row'>\n<div class='col-sm-12'>Click <span class='cart_qty_controls'>'+'</span> to increase the quantity of a product in your cart, <span class='cart_qty_controls'>'-'</span> to reduce it and <span class='cart_qty_controls'>'x'</span> to remove it.</div>\n</div>\n";

		$tableheading = "<div class='row cart_heading'>\n<div class='col-sm-6'>Description</div>\n<div class='col-sm-3'>Options</div>\n<div class='col-sm-1'>Qty</div>\n<div class='col-sm-1'><span class='pull-right'>Each</span></div>\n<div class='col-sm-1'><span class='pull-right'>Total</span></div>\n</div>\n";

		$out['VIEWCART'] .= $tableheading;
		$out['CHECKOUT'] .= $tableheading;
		$out['EMAIL'] .= "<tr valign='top' style='font-family: Arial, Helvetica, sans-serif;font-size: 12px; font-weight:bold; color: black; background:#C0C0C0;' align='left'><td>Qty</td><td>Description</td><td>Options</td><td>Each</td><td>Total</td></tr>\n";



		$tr=0; //keep track of what line we are on just to set a b/g colour on odd table rows
		for($i=0; $i < $cart_rows; $i++)
			{
			$cart_line_id = $cart_contents['ROWS'][$i]['cart_line_id'];

			// Change here indicates a new product as each may have multiple rows depending on the options.
			// Output the line item and reset all the per line vars so this block only runs when moving onto the next product
			if (($this_cart_line_id != $cart_line_id)) 
				{
				if ( $this_cart_line_id > 0) // output previous product (bar first iteration of loop)
					{
					if (CURRENCY_CONVERSION_METHOD == "FX")
						{// price is base price * FX rate (use $prod_price as is, if PC pricing)
						$prod_price = $prod_price * $fx_rate;
						$prod_price = round_price($prod_price, $rounding);
						}

					// apply qty discounts if this product has qty discounts set, and is ordered in sufficient qty
					if (array_key_exists($this_cart_line_id, $discounted_products_array))
						{
						$discount_percantage = $discounted_products_array[$this_cart_line_id]['DISCOUNT'];
						$apply_to_options = $discounted_products_array[$this_cart_line_id]['OPTIONS'];

						$full_product_price = $prod_price + $options_price;
						$full_product_price_str = "<br><span style='text-decoration:line-through;color:#669900;'>(" . $currency_symbol . number_format($full_product_price, 2) . ")</span>";

						if ($apply_to_options) // note rounding of discount result for both FX & PC as discount may result in > 2 decimal places 
							{// discount basic prod price including option price - must discount/round separately as they are stored that way in order_* tables
							$prod_price = round_price($prod_price * ((100-$discount_percantage) / 100), $rounding) + round_price($options_price * ((100-$discount_percantage) / 100), $rounding);
							}
						else
							{// just discount basic prod price, then add option price
							$prod_price = round_price($prod_price * ((100-$discount_percantage) / 100), $rounding);
							$prod_price += $options_price;
							}

						$product_price_str = $currency_symbol . number_format($prod_price, 2) . $full_product_price_str;
						$line_price = $prod_price * $quantity ;
						$line_price_str = $currency_symbol . number_format($line_price, 2);
						}
					else
						{// no discount set or not enough qty
						$prod_price += $options_price;
						$product_price_str = $currency_symbol . number_format($prod_price, 2);
						$line_price = $prod_price * $quantity ;
						$line_price_str = $currency_symbol . number_format($line_price, 2);
						}

					$total_price += $line_price;
					$line_weight = $weight * $quantity ;
					$total_weight += $line_weight ;
					$qty_controls = qty_controls($this_cart_line_id, $session_int_id, $quantity);


					if($tr % 2)
						{
						$colour="#dcdcdc"; // site main bg
						$checkoutcolour="white";
						}
					else
						{
						$colour="#eee";
						$checkoutcolour="#eee";
						}
					$tr++;


					$out['VIEWCART'] .= "<div style='background-color:$colour;' class='row'>\n
						<div class='col-sm-6'><b>$product_code</b> $customer_title</div>\n
						<div class='col-sm-3'>$option_desc</div>\n
						<div class='col-sm-1'><span class='cart_price_label'>Quantity</span> <span class='pull-right'><span class='cart_qty'>$quantity</span>&nbsp;&nbsp; $qty_controls</span> </div>\n
						<div class='col-sm-1'><span class='cart_price_label'>Each</span><span class='pull-right'>$product_price_str</span></div>\n
						<div class='col-sm-1'><span class='cart_price_label'>Total</span> <span class='pull-right'> $line_price_str</span></div>\n
					</div>\n";
					

					$out['CHECKOUT'] .= "<div style='background-color:$checkoutcolour;' class='row'>\n
						<div class='col-sm-6'><b>$product_code</b> $customer_title</div>\n
						<div class='col-sm-3'>$option_desc</div>\n
						<div class='col-sm-1'><span class='cart_price_label'>Quantity</span> <span class='pull-right'><span class='cart_qty'>$quantity</span></span> </div>\n
						<div class='col-sm-1'><span class='cart_price_label'>Each</span><span class='pull-right'>$product_price_str</span> </div>\n
						<div class='col-sm-1'><span class='cart_price_label'>Total</span><span class='pull-right'>$line_price_str</span></div>\n
					</div>\n";

					$out['EMAIL'] .= "<tr  bgcolor='$colour' style='font-family: Arial, Helvetica, sans-serif;font-size: 12px; color: black;' valign='top' align='left'><td><b>$quantity</b></td><td><b>$product_code</b> $customer_title</td><td style='font-family: Arial, Helvetica, sans-serif;font-size: 11px; color: black;'>$option_desc</td><td>$product_price_str</td><td>$line_price_str</td></tr>\n";



					} // END if ( $this_cart_line_id > 0) // output previous product (bar first iteration of loop)
		
				if ($cart_line_id == 3000000000) 
					break;


				$weight = $cart_contents['ROWS'][$i]['weight'];// only need to get these from d/b once per line item, will not change 
				$quantity = $cart_contents['ROWS'][$i]['quantity'];
				$product_id = $cart_contents['ROWS'][$i]['product_id'];
				$product_code = $cart_contents['ROWS'][$i]['product_code'];
				$prod_price = $cart_contents['ROWS'][$i]['prod_price'];
				$customer_title = $cart_contents['ROWS'][$i]['customer_title'];

				// then reset vars
				$options_price = 0;
				$line_price = 0;
				$option_desc="";

				$this_cart_line_id = $cart_line_id;
				}// END if (($this_cart_line_id != $cart_line_id))


			$option_type_id = $cart_contents['ROWS'][$i]['option_type_id'];
			$option_name = $cart_contents['ROWS'][$i]['option_name'];
			$incart_option_id = $cart_contents['ROWS'][$i]['incart_option_id'];
			$extra_cost = $cart_contents['ROWS'][$i]['extra_cost'];
			$ticked_value = $cart_contents['ROWS'][$i]['ticked_value'];
			$opt_price = $cart_contents['ROWS'][$i]['opt_price'];
			$typed_value = $cart_contents['ROWS'][$i]['typed_value'];
			$option_type = $cart_contents['ROWS'][$i]['option_type'];
		
			if (!is_null($option_type_id)) // check this product has at least one option
				{
				if ($incart_option_id > 0) // and current option choses
					{
					$option_desc .= $option_name . ": " ;

					if ($option_type == "USERTEXT") 
						$option_desc .= $typed_value . "<br />";
					else
						$option_desc .= $ticked_value . "<br />";

					if ($extra_cost) 
						{
						if (CURRENCY_CONVERSION_METHOD == "FX")
							{// price is base price * FX rate (use $opt_price as is, if PC pricing)
							$opt_price = $opt_price * $fx_rate;
							$opt_price = round_price($opt_price, $rounding);
							}
						$options_price+=$opt_price;
						}
					}
				} // END if (!is_null($option_type_id))
			} // END for($i=0; $i < $cart_rows; $i++)



		// shipping
		$weight_str= number_format($total_weight, 2);

		if ($total_weight > 10)
			$total_weight = 10;
		$shipping_q = "select min(price) as ship_cost from $shipping_rates_table 
				where :total_weight <= weight and zone=:ship_zone";
				
		// need to bind $total_weight as a string, otherwise decimal value will be truncated (4.25 to 4)
		$shipping_parameters = array(
			array('P' => ':total_weight', 'V'=> (string)$total_weight, 'T' => 'S'),
			array('P' => ':ship_zone', 'V'=> $ship_zone, 'T' => 'S')
			);		

		$shipping = db_single_prepared_query($shipping_q, $shipping_parameters);

		$base_ship_cost = $shipping['ROWS'][0]['ship_cost'];

		// base cost is in euro, * by fx rate
		$ship_cost = $base_ship_cost * $fx_rate;
		$ship_cost = round_price($ship_cost, $rounding);
		$ship_cost_str =  $currency_symbol . number_format($ship_cost, 2);




		$out['VIEWCART'] .= "<div class='row'>\n<div class='col-sm-12'>&nbsp;</div></div>\n";
		$out['CHECKOUT'] .= "<div class='row'>\n<div class='col-sm-12'>&nbsp;</div></div>\n";
		$out['EMAIL'] .= "<tr><td colspan='5'>&nbsp;</td></tr>\n";


		$out['VIEWCART'] .= "<div class='row'>\n<div class='col-sm-12'><a href='$change_country_link'>Shipping - $weight_str Kgs to <b>$country_name</b> <i class='fa fa-lg fa-cog' aria-hidden='true'></i></a> <span class='pull-right'>$ship_cost_str</span></div>\n</div>\n";
		$out['CHECKOUT'] .= "<div class='row'>\n<div class='col-sm-12'>Shipping - $weight_str Kgs to <b>$country_name</b> <span class='pull-right'>$ship_cost_str</span></div>\n</div>\n";
		$out['EMAIL'] .= "<tr bgcolor='#C0C0C0' style='font-family: Arial, Helvetica, sans-serif;font-size: 12px; color: black;'><td>&nbsp;</td><td colspan='3'>Shipping - $weight_str Kgs to <b>$country_name</b></td><td>$ship_cost_str</td></tr>\n";



		$total_price += $ship_cost;

		
		$total_price_str =  $currency_symbol . number_format($total_price, 2);
	
		$tablesummary = "<div class='row'>\n<div class='col-sm-12'><b>Grand total, with shipping</b> <span class='pull-right cart_grand_total'>$total_price_str</span></div>\n</div>\n";

		$out['VIEWCART'] .= $tablesummary;
		$out['CHECKOUT'] .= $tablesummary;
		$out['EMAIL'] .= "<tr style='font-family: Arial, Helvetica, sans-serif;font-size: 12px; font-weight:bold; color: black;'><td align='right' colspan='4'>Grand Total, inclusive of Shipping	</td><td align='left'>$total_price_str</td></tr>\n";



		$tableclose = "\n\n\n\n\n\n</div> <!-- / container-fluid-->\n";

		$out['VIEWCART'] .= $tableclose;
		$out['CHECKOUT'] .= $tableclose;
		$out['EMAIL'] .= "</table></center>\n";

		$out['HASPRODUCTS'] = true;
		$out['SHIPPING'] = $base_ship_cost;// this is just to maintain a record of the shipping cost applied and in euro regardless of the current currency chosen
		$out['GRANDTOTAL'] = $total_price;
		$out['WEIGHT'] = $total_weight;
		} // END if ($cart_rows == 0) FALSE

	
	return $out;
	} // END function display_cart_contents() 



?>