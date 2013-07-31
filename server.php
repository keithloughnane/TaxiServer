<?php
	/* 
	Taxa server to be used with Taxa iPhone App.
	Copyright 2011 <name>
	V1 Writen by Keith Loughnane (loughnanedevelopment@gmail.com)
	*/
	//Only use echo of device data
	//Use debugMsg for debuging
	
	
	//TODO Time
require_once ("gps_class.php");

$AproachDist = 5; //KM
$ArriveDist = 0.5; //KM
$timeoutTime = (5*60); //Seconds
$id = $_GET['id'];
$url = $_SERVER[SCRIPT_NAME]."?".$_SERVER[QUERY_STRING]; 
$debug = false;


//Setup MySQL connection

$conn = mysql_connect("mysql9.unoeuro.com","needataxino_com","opjunk64");
mysql_select_db("needataxinow_com_db",$conn);

//See if there is anything to be done, most timeouts for inactive devices

 //Uncomment to reset
/*
$sqlStmnt = "delete from messages";
$result = mysql_query($sqlStmnt,$conn);
$sqlStmnt1 = "delete from devices";
$result = mysql_query($sqlStmnt1,$conn);
$sqlStmnt2 = "delete from pickupCalc";
$result = mysql_query($sqlStmnt2,$conn);
$sqlStmnt3 = "delete from pickups";
$result = mysql_query($sqlStmnt3,$conn);
*/

		debugMsg("BEGINING INTERACTION URL=" . $url);
		//housekeeping();

if(isset($_GET['msgtype']))
{
	$messageType = $_GET['msgtype'];
	// Figure out which message was recieved and hand off
	switch ($messageType)
	{
		/*case "debug":
		{
		debugMsg( "*********************************************************************************************************************");
		//----------Debug Mode---------------------------
			$id = -1;
			header( 'refresh: 5; url='.$url );
			//recievedGetMessages(-1);
			$debug = true;
		}
		break;*/
		

		
	case "setPosition":
		{
		
		
		//preg_replace('/[^a-zA-Z0-9\ .-]/','',$var) ;
		
		
		//-----------Device Setting Possition-----------------
		echo("setPosition in switch");
		$id =  mysql_real_escape_string($_GET['id']);

			$llong = mysql_real_escape_string( $_GET['llong']);

			//$llat = $_GET['llat'];
			$llat = mysql_real_escape_string($_GET['llat']);

			recievedSetPosition($id, $llong, $llat);

		}
		break;
	case "requestPickup":
		{
		//----------Device/Customer Requesting Pickup--------
			$id = mysql_real_escape_string( $_GET['id']);

			$llong = mysql_real_escape_string( $_GET['llong']);

			$llat = mysql_real_escape_string( $_GET['llat']);

			recievedRequestPickup($id, $llong, $llat);
		}
		break;
	case "acceptPickup":
		{
		//----------Device/Taxi Accepting Pickup Request-------
			$id =  mysql_real_escape_string($_GET['id']);

			$pickupid =  mysql_real_escape_string($_GET['pickupid']);

			recievedAcceptPickup($id, $pickupid);
		}
		break;
	case "refusePickup":
		{
		//----------Device/Taxi Refusing Pickup Request--------
			$id =  mysql_real_escape_string($_GET['id']);

			$pickupid =  mysql_real_escape_string($_GET['pickupid']);

			recievedRefusePickup($id, $pickupid);
		}
		break;
	case "activate":
		{
		//----------Device is checking to keep active-----------
			$id =  mysql_real_escape_string($_GET['id']);

			$mode =  mysql_real_escape_string($_GET['mode']);

			debugMsg("Activate ID=" . $id . "Mode = " . $mode);
			recievedActivate($id,$mode);
		}
		break;
	case "deActivate":
		{
		//----------Device is deactivating (like logoff)-----------
			$id =  mysql_real_escape_string($_GET['id']);

			recievedDeactivate($id);
		}
		break;
	case "getMessages":
		{
		//---Getting Messages, All responses from server come through this---
			$id =  mysql_real_escape_string($_GET['id']);

			recievedGetMessages($id);
		}
		break;
	default:
		echo "Request not understood";
		break;
	}
}
else //If no message was recieved they probably aren't using the app
{
	echo "TaxAppV0.1<br>Please use the app.";
}

function recievedGetMessages($fromDeviceID)							//GetMessages is the servers way of talking TO the device
{	


	debugMsg("RCV GetMessages id=".$fromDeviceID);
	
	global $conn;
	//Update
	$sqlStmnt = "select * from messages where receiverID = " .$fromDeviceID. " and status = 0";
	//debugMsg($sqlStmnt);
	$result = mysql_query($sqlStmnt,$conn);
	$numRows = mysql_num_rows($result);
	//echo $numRows;
	echo "MSGSTART\n";
	while($newArray = mySql_fetch_array($result))
	{
		$txt  = $newArray['msgText'];
		echo $txt."\n";
	}
	echo "MSGEND\n";
	//temp removed for iPhone testing, put back when done
	//Mark message as Delivered
	if( $_GET['msgtype'] != "debug")
	{
	$sqlStmnt1 = "update messages set status = 1, delivered ='". "2001-01-01 13:00:30" ."' where receiverID = " .$fromDeviceID . " and status = 0";
	$result = mysql_query($sqlStmnt1,$conn);
	}
}

function registerDevice()
{
	//Todo
}

function recievedSetPosition($fromDeviceID, $llong, $llat) 		//Device is updating it's position with server
{
	$time = time();
	$stime = date("Y-m-d H:i:s") ;
	debugMsg($stime);
	global $conn;
	debugMsg("RCV SetPosition id=".$fromDeviceID." llong=".$llong." llat=".$llat);
	$sqlStmnt = "update devices set llong = " . $llong . ", llat = " . $llat . " ,  lastActive = " . " '" . $stime .  "' " . " where deviceId = " . $fromDeviceID;
	debugMsg("about to update position");
	echo $sqlStmnt;
	debugMsg($sqlStmnt);
	$result = mysql_query($sqlStmnt,$conn);
	debugMsg($result);
	updateDistances($fromDeviceID); //Used for updating ongoing pickups.
}
	
function recievedActivate($fromDeviceID,$mode) 								//This updates the active time so it doesn't timeout
{
	global $conn;
	debugMsg("RCV Activate id=".$fromDeviceID);
	$time = time();
	$stime = date("Y-m-d H:i:s") ;
	
	//If Device isn't registered it must be
	
	$sqlStmnt = "select deviceId from devices where deviceId =" .  $fromDeviceID . " and master = " . $mode;
	$result = mysql_query($sqlStmnt,$conn);
	
	
	
	
	$numRows = mysql_num_rows($result);
	
	if($numRows < 1)
	{
		debugMsg("numRows<1/Device not registered Registering");
		$sqlStmnt0 = "delete from devices where deviceId = " . $fromDeviceID;
		//$sqlStmnt = "insert into devices ( deviceId, lastActive, active ) values (" .  $fromDeviceID . ", " . $stime . ", true";
		$result0 = mysql_query($sqlStmnt0,$conn);
		//Remove device (in case of swithing mode)
		//Not registered register it
		debugMsg($sqlStmnt0);

		$sqlStmnt1 = "insert into devices ( deviceId, lastActive, master , active,available ) values (" .  $fromDeviceID . ", '" . $stime .  "' , " . $mode . ", 1 , 1)";
		$result1 = mysql_query($sqlStmnt1,$conn);
		
		
		
		$numRows1 = mysql_num_rows($result1);
			debugMsg($sqlStmnt1);
	}
	else
	{
		debugMsg("device already rgistered, checking in");
		global $conn;
		$sqlStmnt = "update devices set lastActive ='". $stime ."', active = ". true;
		$result = mysql_query($sqlStmnt,$conn);
	}
}

/*------------------------------------
When the server recieves a pickup request it calls
recievedRequestPickup();
This in turn calls
calcDistances();
Which creates a list of available Taxis and distances then recievedRequestPickup calls
findCab()
To select the nearest cab, on refusal it is called again and agin going through the list of cabs until
pickupAccepted()
is called accepting the pickup or
completeRefusal()
is reached meaning there are no more taxis left on the list.
  ------------------------------------*/

function recievedRequestPickup($fromDeviceID, $llong, $llat)	//Device is asking for a Taxi pickup
{
	$time = time();
	$stime = date("Y-M-d H:i:s") ;
	
	debugMsg("RCV RequestPickup id=".$fromDeviceID." llong=".$llong." llat".$llat);
	global $conn;
	$sqlStmnt = "SELECT MAX(pickupId) FROM pickups";
	$result = mysql_query($sqlStmnt,$conn);
	$numRows = mysql_num_rows($result);
	debugMsg("num of rows: ".$numRows);
	$newArray = mySql_fetch_array($result);
	$sqlStmnt = "insert into pickups (pickupId, taxiID, clientID, llong, llat ,StartTime ,status) 
		values (".($newArray[0]+1) .",-1,".$fromDeviceID.",".$llong.",".$llat.", '" . $stime . "' ,0);";
	calcDistances($llat,$llong,($newArray[0]+1));
	debugMsg($sqlStmnt);
	$result = mysql_query($sqlStmnt,$conn);
	findCab(($newArray[0]+1));
}

function calcDistances($llat,$llong,$pickupID)							//This Method is used to create a list of taxis with distances to the apropriate cab can be called
{
	debugMsg("--Creating Distance Calculations");
	global $conn;
	$sqlStmnt = "select deviceId, llong, llat from devices where master = true and active = true and available = true";
	debugMsg($sqlStmnt);
	$result = mysql_query($sqlStmnt,$conn);
	$numRows = mysql_num_rows($result);
	debugMsg("num of rows: ".$numRows);
	while($newArray = mySql_fetch_array($result))
	{
		$distance = new calcMiles ($llat, $llong, $newArray['llat'], $newArray['llong'], "kilometer");
		$numericalDistance = $distance->lastResult;
		debugMsg("----From Lat:".$llat." long:".$llong." PickupID:".$pickupID."To Lat:".$newArray['llat']." Long:".$newArray['llong']." Id".$newArray['deviceId']." ->".$numericalDistance."KM");
		$sqlStmnt1 = "SELECT MAX(calcId) FROM pickupCalc";
		debugMsg("----" . $sqlStmnt1);
		$result1 = mysql_query($sqlStmnt1,$conn);
		$numRows1 = mysql_num_rows($result1);
		debugMsg("----" .  "num of rows: ".$numRows1);
		$newArray1 = mySql_fetch_array($result1);
		debugMsg(gettype($pickupID));
		$DpickupID = $pickupID;
		//not working    
		$sqlStmnt2 = "insert into pickupCalc ( calcId, pickupId, taxiDeviceId,  distance) values (" . ($newArray1[0]+1) . "," . $DpickupID . "," . $newArray['deviceId'] .",". $numericalDistance .")";
		
		//$sqlStmnt2 = "insert into pickupCalc (calcId, pickupId, taxiDeviceId, distance) values (5,20,66,15642.051111166)";
		//STatment works when entered manualy
		debugMsg( $sqlStmnt2);
		
		 mysql_error(mysql_query($sqlStmnt2,$conn));
		
		//debugMsg($result2);
	}
}

function findCab($pickupId)															// This Method Finds the nearest Cab for the Client
{
	global $conn;
	debugMsg("--FindCab For Pickup " .  $pickupId);
	$sqlStmnt1 = "SELECT MIN(distance) FROM pickupCalc where pickupId = ".$pickupId;
	$result1 = mysql_query($sqlStmnt1,$conn);
	debugMsg("--" . $sqlStmnt1);
	debugMsg("FIND CAB HAS " . mysql_num_rows($result1) . " CABS AVAILABLE");

	$newArray1 = mySql_fetch_array($result1);
	debugMsg((Double) $newArray1[0]);
	
	
	if ((Double)$newArray1[0] == 0.0)
	{
		debugMsg("--No Taxis available");
		sendPickupTotalRefused($pickupId,"No Taxis Available");
		return;
	}
	$sqlStmnt = "select taxiDeviceId from pickupCalc where pickupId = ".$pickupId ." and distance = ". $newArray1[0];
	$result = mysql_query($sqlStmnt,$conn);
	debugMsg($sqlStmnt);
	$newArray = mySql_fetch_array($result);
	debugMsg($newArray['taxiDeviceId']);
	$sqlStmnt2 = "SELECT llong, llat from pickups where pickupId = " . $pickupId;
	debugMsg($sqlStmnt2);
	$result2 = mysql_query($sqlStmnt2,$conn);
	$newArray2 = mySql_fetch_array($result2);
	debugMsg($newArray2['llong'].$newArray2['llat']);
	sendRequestPickup($newArray['taxiDeviceId'],$newArray2['llong'],$newArray2['llat'],$pickupId);
}

function sendRequestPickup($toDeviceID,$llong,$llat,$pickupID) 		//Send a pickup request to Taxi
{
	//debugMsg("Testing debug");
	
	debugMsg($toDeviceID . "PICKREQ:".$pickupID.":".$llong.":".$llat);
	createMessage($toDeviceID,"PICKREQ:".$pickupID.":".$llong.":".$llat);
}

function sendPickupAccepted($toDeviceID, $pickupID) 				//Taxi Accepts a pickup request
{
	//debugMsg("Testing debug");
	createMessage($toDeviceID,"PICKACC:".$pickupID);
	clearPickupCalc($pickupID);
}

function sendPickupTotalRefused($pickupID,$msg) 						//Find Cab has reached end of Taxi list, none at all available
{
	debugMsg("Pickup Complete Refusal");
	global $conn;
	
	//Get Client ID to notify them
	$sqlStmnt1 = "select clientID from pickups where pickupId = " . $pickupID;
	debugMsg($sqlStmnt1);
	$result1 = mysql_query($sqlStmnt1,$conn);
	$newArray1 = mySql_fetch_array($result1);
	debugMsg($newArray1['clientID']);
	
	//Notify them
	createMessage($newArray1['clientID'],"PICKFAIL:".$pickupID.":".$msg);
	
	//Set pickup status to fail
	$sqlStmnt2 = "update pickups set status = -2 where pickupId = " . $pickupID;
	debugMsg($sqlStmnt2);
	$result2 = mysql_query($sqlStmnt2,$conn);
	
	//Dump rough work
	//clearPickupCalc($pickupID);
}

function sendPickupCaneled($toDeviceID1,$toDeviceID2, $pickupID)//Pickup has been canceled by someone
{
 	debugMsg("--Sending pickup canceled");
	createMessage($toDeviceID1,"PICKCAN:".$pickupID);
	createMessage($toDeviceID2,"PICKCAN:".$pickupID);
	
	//Set pickup status to cancle
	$sqlStmnt2 = "update pickups set status = -2 where pickupId = " . $pickupID;
	debugMsg($sqlStmnt2);
	$result2 = mysql_query($sqlStmnt2,$conn);
	
	clearPickupCalc($pickupID);
}

function sendPickupNear($toDeviceID1,$toDeviceID2, $pickupID)
{
	debugMsg("--TAXI NEAR");
	
	createMessage($toDeviceID1,"PICKNEAR:".$pickupID);
	createMessage($toDeviceID2,"PICKNEAR:".$pickupID);
}

function sendPickupArrived($toDeviceID1,$toDeviceID2, $pickupID)		//The Taxi has Arrived
{
	debugMsg("--TAXI ARRIVED");
	createMessage($toDeviceID1,"PICKARR:".$pickupID);
	createMessage($toDeviceID2,"PICKARR:".$pickupID);
	
	//Set pickup status to Comple
	$sqlStmnt2 = "update pickups set status = -2 where pickupId = " . $pickupID; //TO DO change to status comple and time
	debugMsg($sqlStmnt2);
	$result2 = mysql_query($sqlStmnt2,$conn);
	clearPickupCalc($pickupID);
}

function recievedAcceptPickup($fromDeviceID, $pickupID)
{
	$time = time();
	$stime = date("Y-M-d H:i:s") ;

	//Done for now, Not Tested
	//Todo fix datetime
	debugMsg("RCV AcceptPickup id=".$fromDeviceID." pickupId=".$pickupID);
	global $conn;
	//Update Pickups
	$sqlStmnt = "update pickups set taxiID = ". $fromDeviceID .", status = ". 1 .", StartTime = '". $stime ."' where pickupId = ".$pickupID;
	//Get Client ID to notify them
	$sqlStmnt1 = "select clientID from pickups where pickupId = " . $pickupID;
	debugMsg($sqlStmnt1);
	$result1 = mysql_query($sqlStmnt1,$conn);
	$newArray1 = mySql_fetch_array($result1);
	debugMsg($newArray1['clientID']);
	//Notify them
	createMessage($newArray1['clientID'],"PICKACC:".$pickupID);
	//Drop roughwork
	clearPickupCalc($pickupID);

	$result = mysql_query($sqlStmnt,$conn);
}

function recievedRefusePickup($fromDeviceID, $pickupID)
{
	debugMsg("RCV RefusePickup id=".$fromDeviceID." pickupId=".$pickupID);
	global $conn;
	//Update
	$sqlStmnt = "delete from pickupCalc where taxiDeviceId = " . $fromDeviceID . " and pickupId = ".$pickupID;
	debugMsg($sqlStmnt);
	$result = mysql_query($sqlStmnt,$conn);
	findCab($pickupID);
}

function recievedDeactivate($fromDeviceID)
{
	$time = time();
	$stime = date("Y-M-d H:i:s") ;
	
	debugMsg("RCV Deactivate id=".$fromDeviceID);
	global $conn;
	//Update
	$sqlStmnt = "update devices set lastActive = '". $stime ."', active = ". false;
	$result = mysql_query($sqlStmnt,$conn);
}

function createMessage($toID,$msg)
{
	$time = time();
	$stime = date("Y-M-d H:i:s") ;
	
	//Todo unique ID
	//debugMsg("RCV GetMessages id=".$toID . "MSG>>" . $msg);
	global $conn;
	//Update
	$sqlStmnt = "insert into messages (messageID,receiverID,msgText,status,created) values(" . 1 . "," . $toID . ",'".$msg."'," . 0 . ",'". $stime ."')";
	$result = mysql_query($sqlStmnt,$conn);
	//debugMsg($result);
	//$numRows = mysql_num_rows($result);
}

function housekeeping()
{
		
debugMsg("Starting Housekeeping");	//TODO
	//It is important for efficency that this not run too often
	//There for we need a way to chack last time this ran
	//Investigate system variables
	//if lastTimoutCheck > x
//	{
		doTimeOuts();
//	}
}

function doTimeOuts()
{
	global $conn;
	$time = time();
	
	$time->modify("-" . $timeoutTime . "minutes");
	
	//TIMEDIFF('2009:01:08 00:00:00', '2008:01:01 00:00:00.000001');
	
	$stime = date("Y-M-d H:i:s") ;
	$sqlStmnt =	"update devices set active = false where lastActive < '" . $stime . "'";
	$result = mysql_query($sqlStmnt,$conn);
	//Pickup Requests
	//select calcId, pickupId from calcTable where offerd < now()-PIKREQtimoutTime();
	//remove from calcTable where calcID = calcID[i];
	//findCab(pickupID);

	//Active Timeouts
	//select deviceId where lastActive < now() - DEVACTtimeoutTime();
	//update active = false where deviceId = deviceId[i];
}
function clearPickupCalc($pickupID)
{
	//Done not tested
	debugMsg("Clearing pickup table for pickup:" . $pickupID);
	//Used after pickupRequest has been resloved or refused completly to remove calc entries
	global $conn;
	$sqlStmnt = "delete from pickupCalc where pickupId = " . $pickupID;
	$result = mysql_query($sqlStmnt,$conn);
	debugMsg($result);
}
function updateDistances($taxiID)
{
	//TODO Clean up this message
	//Updates Distances For Working Pickups
	//Only updates if Device is a taxi (both client and taxi would be redundant and the taxi is the one moving)
	//Only updates if the Device is on a pickup. Otherwise there is nothing to calc and so a waste of resources.
	debugMsg("Updatinging distances");
	global $conn;
	//Update
	$sqlStmnt = "select pickupId,clientID from pickups where taxiID = " .$taxiID. " and status = 1";
	debugMsg($sqlStmnt);
	$result = mysql_query($sqlStmnt,$conn);
	$numRows = mysql_num_rows($result);
	debugMsg("Returned >>" . $numRows);
	debugMsg("--CalcDistances Retreved");
	debugMsg("--in loop");
	while($newArray = mySql_fetch_array($result))
	{
		
		$txt  = $newArray['pickupId<br>'];
		debugMsg("--" . $txt."<< pickupid" );
		$clientID  = $newArray['clientID'];
		$pickUpUD = $newArray['pickupId'];
		$txt  = $newArray['clientID'];
		debugMsg("--" . $txt . "<< clientID\n<br>");
		
		//Get both Locations
		//Taxi	
		$sqlStmnt1 = "select llong , llat from devices where deviceId = " . $taxiID;
		debugMsg("--" . $sqlStmnt1);
		
		$result1 = mysql_query($sqlStmnt1,$conn);
		$numRows1 = mysql_num_rows($result1);
		
		debugMsg("--" . "S1 Returned  " . $numRows1 . "<br>");
		//	echo $numRows1;
		
		$newArray2 = mySql_fetch_array($result1);
		
		$txt  = $newArray2['llong'];
		$TLong  = $newArray2['llong'];
		debugMsg("--" . $txt."<< tllong\n<br>");
		//echo $newArray2[1];
		$txt2  = $newArray2['llat'];
		$TLat = $newArray2['llat'];
		debugMsg("--" . $txt2 . "<< Tllat\n<br>");
		
		//Customer
		$sqlStmnt3 = "select llong, llat from devices where deviceId = " . $clientID;
		
		debugMsg("--" . $sqlStmnt3);
		$result2 = mysql_query($sqlStmnt3,$conn);
		$numRows2 = mysql_num_rows($result2);
		
		$newArray3 = mySql_fetch_array($result2);
		
		$txt  = $newArray3['llong'];
		$CLong  = $newArray3['llong'];
		debugMsg("--" . $txt."<< Cllon\n<br>");
		$txt  = $newArray3['llat'];	
		$CLat  = $newArray3['llat'];
		debugMsg("--" . $txt . "<< Cllat\n<br>");
		
		debugMsg("--" . "PreDistCalcTest " . $CLong . "," . $CLat  . "," . $TLong  . "," .  $TLat);
		$distance = new calcMiles ($CLat, $CLong, $TLat, $TLong, "kilometer");
				$distance = new calcMiles ($CLat, $CLong, $TLat, $TLong, "kilometer");
		$numericalDistance = $distance->lastResult;
			debugMsg("--" . "newDistance " .  $numericalDistance);
		// Test for about to arrive
		$AproachDist = 5; //KM
$ArriveDist = 0.5; //KM
		
		debugMsg("AproachDist" . $AproachDist . "ArriveDist" . $ArriveDist);
				if($numericalDistance< $ArriveDist)
		{
				debugMsg("Inside arrivet");
			hasArrived($pickUpId, $taxiID ,$clientID);
		}
		else if($numericalDistance < $AproachDist )
		{
		debugMsg("Inside 5 Min warnign");
			fiveMinWarning($pickUpId, $clientID);
		}



	}
//	echo "MSGEND\n";

	//Write app first
//		select taxiID, clientID llong llat from pickups where status = 0;
	//loop through
	//select llong llat from devices where id = taxiID
	//distance = get distance()
		//if distance < neerenough
			//hasArrived()
		//id distance < 5minDist
			//fiveMinWarning
//echo "yep";
}

function hasArrived($pickup,$taxi,$client)
{
	debugMsg("HAS ARIVED");
	//TODO Pickup update, cleanup
	$time = time();
	$stime = date("Y-M-d H:i:s") ;
	
	$sqlStmnt3 = "update pickups set status = 1 completedTime = " . $stime . " where pickupId = " . $pickupId;
	debugMsg($sqlStmnt3);
	//$result2 = mysql_query($sqlStmnt3,$conn);
	
	createMessage($client,"PICKARR" . $pickup);
	createMessage($taxi,"PICKARR" . $pickup);
	//Write app first
	//notify client
	//update pickups as success
}

function fiveMinWarning($pickup,$client)
{
	createMessage($client,"PICKNEAR" . $pickup);
}

function debugMsg($msg)
{
global $debug;
if($debug == false)
{
	$time = time();
	global $id;
	createMessage(-1, date("Y-M-d_H:i:s")  . "[" .  $id .   "]".  " >> "  .    $msg . "<br>");
}
}
?>
<END>



