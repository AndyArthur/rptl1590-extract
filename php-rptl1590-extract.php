<?php

// this program requires pdftotext (a linux program) and PHP version 7.2

// first convert PDF to text
$pdfdir = "input-pdf";
$textdir = "output-txt";

// delete old input-text
if (isset($argv[1]) && $argv[1] == 'delete') {
	echo "Deleting old conversions ...\n";
	system("rm $textdir/*");
}

foreach (scandir($pdfdir) as $file) {
	if (substr($file, -4) !== '.pdf') {
		continue;
	}
	
	$textfile = substr($file, 0, -4).".txt";
	$town = substr($file, 0, -4);
	
	echo ("#### START $town #### \n");
	
	if (file_exists("$textdir/$textfile")) {
		echo "Text file exists, not converting PDF again (arg[1] == delete to override).\n";
	}
	else {
		echo "Converting to text file ...";
		system('pdftotext -layout '.escapeshellarg("$pdfdir/$file").' '.escapeshellarg("$textdir/$textfile"));
		echo " DONE\n";
	}
	
	$text = file("$textdir/$textfile");
	$town = substr($file, 0, -4);

	$taxroll = array();
	$payerId = 0;
	
	$output = "";
	
	$townId = "";
	$swisId = "";
	$countyId = "";
	$villageId = "";
	
	for ($i = 0; $i < count($text); $i++) {
		if ($i % 100 == 0) echo "#";
		
		// capture county - town - swis
		if (preg_match('/COUNTY\s*?- (.*?)\s{2}/', $text[$i], $matches)) $countyId = $matches[1];
		if (preg_match('/CITY\s*?- (.*?)\s{2}/', $text[$i], $matches)) $townId = $matches[1];
		if (preg_match('/TOWN\s*?- (.*?)\s{2}/', $text[$i], $matches)) $townId = $matches[1];
		if (preg_match('/VILLAGE\s*?- (.*?)\s{2}/', $text[$i], $matches)) $villageId = $matches[1];
		if (preg_match('/SWIS\s*?- (.*?)\s{2}/', $text[$i], $matches)) $swisId = $matches[1];

		
		// first line = tax id
		$pattern = '/\*{3,} ((\d|\-|\.){4,}) \*{3,}/';
		preg_match($pattern, $text[$i], $matches);

		// we've found the start of a new tax record!
		if (isset($matches[1])) {
			$i++;
			
			$taxpayer = array();
			$j = 0;
			// output each part onto the line
			while (isset($text[$i]) && !preg_match('/\*{3,}/', $text[$i])) {
				$split = preg_split('/\s{2,}/', $text[$i]);
				
				$taxpayer[$j] = $split;
				$i++; $j++;
			} 
			
			
			$taxpayer[$j] = array('location',$countyId, $townId, $villageId, $swisId);
						
			$taxroll[$payerId++] = $taxpayer;
			$i--;
		}
	}

	// export unprocess tax rolls for debug
	file_put_contents("output-debug/$town.txt", print_r($taxroll,true));
	
	// next scan for all special district types in file
	$specialDistType = array();
	
	foreach ($taxroll as $taxpayer) {
		for ($i = 0; $i < count($taxpayer); $i++) {
				for ($j = 0; $j < count($taxpayer[$i]); $j++) {
					if (preg_match('/^([A-Z]{2})(\d\d\d) (.*?)( TO|$|\d{2,})/', $taxpayer[$i][$j],$matches)) {
						$specialDistType[$matches[1]] = $matches[1];						
					}
				}	
		}
	}
	ksort($specialDistType);

	// then process into a nice field
	$formTax = array();

	foreach ($taxroll as $taxpayer) {
		$formPayer = array();
		
		$formPayer[0] = $taxpayer[1][0]; // tax id
		
		if (isset($taxpayer[0][1]) && preg_match('/^(\d.*?) (.*?)$/',$taxpayer[0][1], $address)) {
			$formPayer[1] = $address[1]; // street number
			$formPayer[2] = ucwords(strtolower($address[2])); // street name
		}
		elseif (isset($taxpayer[0][1]))  {
			$formPayer[1] = '';
			$formPayer[2] = ucwords(strtolower($taxpayer[0][1])); // street name
		}
		
		if (isset($formPayer[1])) $formPayer[23] = ltrim($formPayer[1].' '.$formPayer[2]); // full street 
		else if (isset($formPayer[1])) $formPayer[23] = ltrim($formPayer[2]);
		
		$formPayer[3] = ucwords(strtolower($taxpayer[2][0])); // owner 1
		
		// next five lines are either are owner or address info
		for ($i = 3; $i < 8; $i++) {
			
			if (!isset($taxpayer[$i][0])) continue;
			
			// if a taxpayer name
			if (preg_match('/^[A-Z]/',$taxpayer[$i][0]) && !preg_match('/^PO/',$taxpayer[$i][0]) && !preg_match('/^(.*?), (\w\w) (.*?)$/',$taxpayer[$i][0])) 	{
				
				if (!isset($formPayer[4])) $formPayer[4] = ucwords(strtolower($taxpayer[$i][0]));
				else if (!isset($formPayer[5])) $formPayer[5] = ucwords(strtolower($taxpayer[$i][0]));
				else if (!isset($formPayer[6])) $formPayer[6] = ucwords(strtolower($taxpayer[$i][0]));
			}
			
			// if a city - state - zip
			else if (preg_match('/^(.*?), (\w\w) (.*?)$/',$taxpayer[$i][0], $address)) {
				$formPayer[10] = ucwords(strtolower($address[1]));
				$formPayer[11] = strtoupper($address[2]);
				$formPayer[12] = ucwords(strtolower($address[3]));
			}
			
			// if an address (pad to this field)
			else if (preg_match('/^\d/',$taxpayer[$i][0]) || preg_match('/^PO/',$taxpayer[$i][0])) {
				if (!isset($formPayer[7])) $formPayer[7] =  ucwords(strtolower($taxpayer[$i][0]));
				else if (!isset($formPayer[8])) $formPayer[8] =  ucwords(strtolower($taxpayer[$i][0]));
				else if (!isset($formPayer[9])) $formPayer[9] =  ucwords(strtolower($taxpayer[$i][0]));
			}
		
		$formPayer[13] = $taxpayer[1][1];
	}
		
		// extract coordinates by searching through array
		for ($i = 0; $i < count($taxpayer); $i++) {
			for ($j = 0; $j < count($taxpayer[$i]); $j++) {
				if (preg_match('/EAST-(\d*) NRTH-(\d*)/', $taxpayer[$i][$j], $coord)) {
					$formPayer[14] = $coord[1];
					$formPayer[15] = $coord[2];		
				}
			}
		}
		
		// extract acres
		
			for ($i = 0; $i < count($taxpayer); $i++) {
			for ($j = 0; $j < count($taxpayer[$i]); $j++) {
				if (preg_match('/ACRES *?(\d+)/', $taxpayer[$i][$j],$acres)) {
					$formPayer[16] = $acres[1];
				}
				else if (preg_match('/ACRES/', $taxpayer[$i][$j])) {
					if (preg_match('/^([0-9.]+)/', $taxpayer[$i][$j+1], $acres)) $formPayer[16] = $acres[1];
				}
			}
		}

	// extract full market value

			for ($i = 0; $i < count($taxpayer); $i++) {
			for ($j = 0; $j < count($taxpayer[$i]); $j++) {
				if (preg_match('/FULL MARKET VALUE *?(\d+)/', $taxpayer[$i][$j],$value)) {
					$formPayer[17] = str_replace(',','',$value[1]);
				}
				else if (preg_match('/FULL MARKET VALUE/', $taxpayer[$i][$j])) {
					if (preg_match('/^([0-9,]+)/', $taxpayer[$i][$j+1], $value)) $formPayer[17] = str_replace(',','',$value[1]);
				}
			}
		}
		
		// extract deed book info
			for ($i = 0; $i < count($taxpayer); $i++) {
				for ($j = 0; $j < count($taxpayer[$i]); $j++) {
					
										
					if (preg_match('/DEED BOOK *?(\d+) *?PG-(\d+)/', $taxpayer[$i][$j],$value)) {
						$formPayer[18] = $value[1];
						$formPayer[19] = $value[2];
					}
					else if (preg_match('/DEED BOOK *?(\d+)/', $taxpayer[$i][$j],$value)) {
						$formPayer[18] = $value[1];
						if (isset($taxpayer[$i][$j+1]) && preg_match('/^PG-(\d+)/', $taxpayer[$i][$j+1], $value)) $formPayer[19] = $value[1];
					}
				}
			}
				
			// county taxable amount
			for ($i = 0; $i < count($taxpayer); $i++) {
				for ($j = 0; $j < count($taxpayer[$i]); $j++) {
					if (preg_match('/COUNTY TAXABLE VALUE/', $taxpayer[$i][$j])) $formPayer[20] = chop(str_replace(',','',$taxpayer[$i][$j+1]));
				}
			}

		// school taxable amount
			for ($i = 0; $i < count($taxpayer); $i++) {
				for ($j = 0; $j < count($taxpayer[$i]); $j++) {
					if (preg_match('/SCHOOL TAXABLE VALUE/', $taxpayer[$i][$j])) $formPayer[21] = chop(str_replace(',','',$taxpayer[$i][$j+1]));
				}
			}	
		// city taxable amount
			for ($i = 0; $i < count($taxpayer); $i++) {
				for ($j = 0; $j < count($taxpayer[$i]); $j++) {
					if (isset($taxpayer[$i][$j]) && preg_match('/^(CITY|TOWN)/', $taxpayer[$i][$j])) {
						if (isset($taxpayer[$i][$j+1]) && preg_match('/^TAXABLE VALUE/', $taxpayer[$i][$j+1])) $formPayer[22] =  chop(str_replace(',','',$taxpayer[$i][$j+2]));
						
					}
				}	
			}
	
		
		// field relating to solar power (for munis that have such laws)
		$formPayer[24] = '';
		for ($i = 0; $i < count($taxpayer); $i++) {
			for ($j = 0; $j < count($taxpayer[$i]); $j++) {
				if (preg_match('/solar/i', $taxpayer[$i][$j])) {
					$formPayer[24] .= "{$taxpayer[$i][$j]},";
				}
			}	
		}	
		
		// STAR
		$formPayer[25] = '';
		for ($i = 0; $i < count($taxpayer); $i++) {
			for ($j = 0; $j < count($taxpayer[$i]); $j++) {
				if (preg_match('/ STAR/', $taxpayer[$i][$j])) {
					$formPayer[25] .= "{$taxpayer[$i][$j]},";
				}
			}	
		}
		
		// STAR
		$formPayer[26] = '';
		for ($i = 0; $i < count($taxpayer); $i++) {
			for ($j = 0; $j < count($taxpayer[$i]); $j++) {
				if (preg_match('/(VET WAR|CW_15_VET|VETWAR|VETDIS|VETERANS)/', $taxpayer[$i][$j])) {
					$formPayer[26] .= "{$taxpayer[$i][$j]},";
				}
			}	
		}	
		
		// SCHOOL
		$formPayer[27] = $taxpayer[2][1];	
		
		// columns 28+ are special districts
		$l = 28;
		
		foreach ($specialDistType as $type) {	
			$formPayer[$l] = '';
				
			for ($i = 0; $i < count($taxpayer); $i++) {
				for ($j = 0; $j < count($taxpayer[$i]); $j++) {
					if (isset($taxpayer[$i][$j]) && preg_match('/^(\w\w)(\d\d\d) (.*?)( TO|$|\d{2,})/', $taxpayer[$i][$j],$matches)) {
						if ($matches[1] == $type) $formPayer[$l] .= "{$matches[1]}{$matches[2]} {$matches[3]} ";
					}
				}	
			}
			
			$l++;
		}
		
		
		// sort and add missing keys
		for ($i = 0; $i < count($formPayer); $i++) {
			if (!isset($formPayer[$i])) $formPayer[$i] = '';
		}
		
		
		ksort($formPayer);
		
				// shift onto the rolls county, town, village, swis
		for ($i = 0; $i < count($taxpayer); $i++) {
				
				if ($taxpayer[$i][0] != 'location') continue;
				
				// add array to line				
				for ($j = count($taxpayer[$i])-1; $j > 0; $j--) array_unshift($formPayer, $taxpayer[$i][$j]);
				
		}
		
		
		$formTax[] = $formPayer;
		
		}


		// lastly sort form by street and number
		
	    $addNum = array();
        $addSt = array();
        $own1 = array();
		for ($i = 0; $i < count($formTax); $i++) {
		  $addSt[] = $formTax[$i][6];
		  $addNum[] = $formTax[$i][5]; 
		  $own1[] =  $formTax[$i][7];
		}

		// now apply sort
		array_multisort($addSt, SORT_ASC, 
				$addNum, SORT_NUMERIC, SORT_ASC,
				$own1, SORT_ASC, 
				$formTax);
				
				
	//print_r($formTax);

	echo "\nWriting to CSV ...";

	// print out form
	$output .=  '"Tax Roll","County","Town","Village","SWIS","Tax ID","Street Number","Street Name","Owner 1","Owner 2","Owner 3","Owner 4",'
				.'"Mail Address 1","Mail Address 2","Mail Address 3","Mail City","Mail State","Mail Zip",'
				.'"Property Type","East","North","Acres","Full Market Value","Deed Book","Deed Pg",'
				.'"County Value","School Value","Town Value","Full Street",'
				.'"Solar","STAR","VETS","School",';
				
	foreach ($specialDistType as $type) {
		$output .= "\"$type\",";
	}
				
	$output .=  "\n";

	foreach ($formTax as $line) {
		$output .=  '"'.$town.'",';
		foreach ($line as $item) {
			$output .=  '"'.$item.'",';
		}
		
		$output .=  "\n";
	}
	
	// save output to file
	file_put_contents("output-csv/$town.csv", $output);
	
	echo " DONE\n";
}

// last, create a great big file
//system("cat output-csv/*.csv > all-property.csv");

system("zip output-csv.zip output-csv/*");

