<?php
class calcMiles {
	var $Lat1 = NULL;
	var $Lon1 = NULL;
	var $Lat2 = NULL;
	var $Lon2 = NULL;
	
	var $units = NULL;
	var $lastResult = NULL;
	var $lastResultFormatted = NULL;
	
	function calcMiles ($Lat1, $Lon1, $Lat2, $Lon2, $units = "miles"){
		$Difference = 3958.75 * acos(  sin($Lat1/57.2958) * sin($Lat2/57.2958) + cos($Lat1/57.2958) * cos($Lat2/57.2958) * cos($Lon2/57.2958 - $Lon1/57.2958));
		
		switch ($units){
			default:
			case "":
			case "miles":
				$this->units = "Miles";
				$Difference = $Difference * 1;
				break;
			case "yards":
				$this->units = "Yards";
				$Difference = $Difference * 1760;
				break;
			case "parsec":
				$this->units = "Parsecs";
				$Difference = $Difference * 0.0000000000000521553443;
				break;
			case "nauticalmiles":
				$this->units = "Nautical Miles";
				$Difference = $Difference * 0.868974087;
				break;
			case "nanometer":
				$this->units = "Nanometers";
				$Difference = $Difference * 1609344000000;
				break;
			case "millimeter":
				$this->units = "Millimeters";
				$Difference = $Difference * 1609344;
				break;
			case "mil":
				$this->units = "Mils";
				$Difference = $Difference * 63360000;
				break;
			case "micrometer":
				$this->units = "Micrometers";
				$Difference = $Difference * 1609344000;
				break;
			case "meter":
				$this->units = "Meters";
				$Difference = $Difference * 1609.344;
				break;
			case "lightyear":
				$this->units = "Light Years";
				$Difference = $Difference * 0.0000000000001701114356;
				break;
			case "kilometer":
				$this->units = "Kilometers";
				$Difference = $Difference * 1.609344;
				break;
			case "inches":
				$this->units = "Inches";
				$Difference = $Difference * 63360;
				break;
			case "hectometer":
				$this->units = "Hectometers";
				$Difference = $Difference * 16.09344;
				break;
			case "furlong":
				$this->units = "Furlongs";
				$Difference = $Difference * 8;
				break;
			case "feet":
				$this->units = "Feet";
				$Difference = $Difference * 5280;
				break;
			case "dekameter":
				$this->units = "Dekameters";
				$Difference = $Difference * 160.9344;
				break;
			case "centimeter":
				$this->units = "Centimeters";
				$Difference = $Difference * 160934.4;
				break;
		}
		
		$this->lastResult = $Difference;
		$this->lastResultFormatted = $this->lastResult . " " . $this->units;
		return $Difference;
	}
}
?>