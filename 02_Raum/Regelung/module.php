<?
$sws = 0;
$zp_conf = true;
$abw = false;
$prog = 2;
$sw = 22;
$sw_abs = 22;
$sws_abw = false;
$at = 10;
$at_2 = 10;
$at_4 = 10;
$at_8 = 10;
	
class HeizungssteuerungRegler extends IPSModule
	{
		
		public function Create()
		{
			//Never delete this line!
			parent::Create();
						
			if (!IPS_VariableProfileExists("Heizung_Programm")) {
			
				IPS_CreateVariableProfile("Heizung_Programm", 1); // 0 boolean, 1 int, 2 float, 3 string,
				IPS_SetVariableProfileValues("Heizung_Programm", 0, 3, 0);
				IPS_SetVariableProfileDigits("Heizung_Programm", 0);
				IPS_SetVariableProfileAssociation("Heizung_Programm", 0, "Aus", "", 0xFFFFFF);
				IPS_SetVariableProfileAssociation("Heizung_Programm", 1, "Eco", "", 0xFFFFFF);
				IPS_SetVariableProfileAssociation("Heizung_Programm", 2, "Comfort", "", 0xFFFFFF);
				IPS_SetVariableProfileAssociation("Heizung_Programm", 3, "Abwesend", "", 0xFFFFFF);
			}
			if (!IPS_VariableProfileExists("Heizung_SWS")) {
			
				IPS_CreateVariableProfile("Heizung_SWS", 1); // 0 boolean, 1 int, 2 float, 3 string,
				IPS_SetVariableProfileValues("Heizung_SWS", 0, 2, 1);
				IPS_SetVariableProfileDigits("Heizung_SWS", 0);
				IPS_SetVariableProfileAssociation("Heizung_SWS", 0, "Aus", "", 0xFFFFFF);
				IPS_SetVariableProfileAssociation("Heizung_SWS", 1, "Hand", "", 0xFFFFFF);
				IPS_SetVariableProfileAssociation("Heizung_SWS", 2, "Auto", "", 0xFFFFFF);
			}
			
			if (!IPS_VariableProfileExists("Heizung_Abs")) {
			
				IPS_CreateVariableProfile("Heizung_Abs", 2); // 0 boolean, 1 int, 2 float, 3 string,
				IPS_SetVariableProfileValues("Heizung_Abs", -3, 3, 0.5);
				IPS_SetVariableProfileDigits("Heizung_Abs", 1);
				IPS_SetVariableProfileText("Heizung_Abs", "", " K");
				IPS_SetVariableProfileIcon("Heizung_Abs",  "Temperature");
			}
			if (!IPS_VariableProfileExists("Heizung_SB")) {
			
				IPS_CreateVariableProfile("Heizung_SB", 1); // 0 boolean, 1 int, 2 float, 3 string,
				IPS_SetVariableProfileValues("Heizung_SB", 0, 2, 1);
				IPS_SetVariableProfileDigits("Heizung_SB", 0);
				IPS_SetVariableProfileAssociation("Heizung_SB", 0, "Aus", "", 0xFFFFFF);
				IPS_SetVariableProfileAssociation("Heizung_SB", 1, "Uebergang", "", 0x80ff00);
				IPS_SetVariableProfileAssociation("Heizung_SB", 2, "Frost", "", 0x0080c0);
			}
		
			
			//___In_IPS_zurverfügungstehende_Variabeln_______________________________________________
			$this->RegisterVariableInteger("SB", "Sonderbetrieb", "Heizung_SB", 0);
			$this->RegisterVariableInteger("SWS", "Softwareschalter", "Heizung_SWS", 1);
			$this->RegisterVariableInteger("prog", "Programm", "Heizung_Programm", 2);
			$this->RegisterVariableFloat("SW", "Sollwert", "~Temperature.Room", 3);
			$this->RegisterVariableFloat("SW_Abs", "Sollwert Absenkung", "Heizung_Abs", 4);
			$this->RegisterVariableFloat("SW_ber", "Sollwert Berechnet", "~Temperature.Room", 5);
			$this->RegisterVariableFloat("AT", "Aussentemperatur", "~Temperature", 6);
			$this->RegisterVariableFloat("AT_2h", "Aussentemperatur +2h", "~Temperature", 7);
			$this->RegisterVariableFloat("AT_4h", "Aussentemperatur +4h", "~Temperature", 8);
			$this->RegisterVariableFloat("AT_8h", "Aussentemperatur +8h", "~Temperature", 9);
			$this->RegisterVariableBoolean("ZP_Conf", "ZP_Confort", "~Switch", 11);
			$this->RegisterVariableBoolean("SWS_Abw", "Abwesenheit", "~Switch", 12);
			$this->RegisterVariableBoolean("Abw", "Abwesend", "~Switch", 15);
			
			//___Modulvariabeln______________________________________________________________________
			$this->RegisterPropertyInteger("SWS", 1);
			$this->RegisterPropertyBoolean("ZP_Conf", true);
			//$this->RegisterPropertyInteger("Test", 0);
			$this->RegisterPropertyInteger("prog", 1);
			//$this->RegisterPropertyFloat("SW", 15);
			//$this->RegisterPropertyFloat("SW_Abs", 3);
			
			//$this->RegisterPropertyBoolean("Abw", true);
			
			
			$this->RegisterPropertyInteger("UpdateWeatherInterval", 30);
			$this->RegisterPropertyString("APIkey", 0);
			$this->RegisterPropertyFloat("Lat", 0);
			$this->RegisterPropertyFloat("Long", 0);
			$this->RegisterPropertyBoolean("WetterForcast", true);
			$this->RegisterPropertyInteger("TrigProgramm", 0);
			$this->RegisterPropertyInteger("TrigConfort", 0);
			$this->RegisterPropertyInteger("TrigAbwesend", 0);
			
			
			//Timer erstellen
			$this->RegisterTimer("UpdateWeather", $this->ReadPropertyInteger("UpdateWeatherInterval"), 'MIN_UpdateWeatherData($_IPS[\'TARGET\']);');
		}
	
	        public function ApplyChanges() {
            		//Never delete this line!
            		parent::ApplyChanges();
			
				
            		$triggerIDProg = $this->ReadPropertyInteger("TrigProgramm");
            		$this->RegisterMessage($triggerIDProg, 10603 /* VM_UPDATE */);
			
			$triggerIDConf = $this->ReadPropertyInteger("TrigConfort");
			$this->RegisterMessage($triggerIDConf, 10603 /* VM_UPDATE */);
			
			$triggerIDAbw = $this->ReadPropertyInteger("TrigAbwesend");
			$this->RegisterMessage($triggerIDAbw, 10603 /* VM_UPDATE */);
			
			//Timerzeit setzen in Minuten
			if ($this->ReadPropertyString("APIkey") != ""){
				$this->SetTimerInterval("UpdateWeather", $this->ReadPropertyInteger("UpdateWeatherInterval")*1000*60);
			}
			
			//Standartaktion Aktivieren
			$this->VariabelStandartaktion();
			
        	}
	
	        public function MessageSink ($TimeStamp, $SenderID, $Message, $Data) {
		global $sws, $zp_conf, $sws_abw, $abw, $prog, $sw, $sw_abs;
            		$triggerIDProg = $this->ReadPropertyInteger("TrigProgramm");
			$triggerIDConf = $this->ReadPropertyInteger("TrigConfort");
			$triggerIDAbw = $this->ReadPropertyInteger("TrigAbwesend");
	
			if (($SenderID == $triggerIDProg) && ($Message == 10603)){// && (boolval($Data[0]))){
				$prog = getValue($this->GetIDForIdent("prog"));
				$sw = getValue($this->GetIDForIdent("SW"));
				$sw_abs = getValue($this->GetIDForIdent("SW_Abs"));
				$this->SWRegler();
           		}
			if (($SenderID == $triggerIDConf) && ($Message == 10603)){// && (boolval($Data[0]))){
				$sws = getValue($this->GetIDForIdent("SWS"));
				$zp_conf = getValue($this->GetIDForIdent("ZP_Conf"));
				$sws_abw = getValue($this->GetIDForIdent("SWS_Abw"));
				$abw = getValue($this->GetIDForIdent("Abw"));
				$this->ProgrammAuswahl();
           		}
			if (($SenderID == $triggerIDAbw) && ($Message == 10603)){// && (boolval($Data[0]))){
				$sws = getValue($this->GetIDForIdent("SWS"));
				$zp_conf = getValue($this->GetIDForIdent("ZP_Conf"));
				$sws_abw = getValue($this->GetIDForIdent("SWS_Abw"));
				$abw = getValue($this->GetIDForIdent("Abw"));
				$this->ProgrammAuswahl();
				if($abw == false){
					//IPS_SetHidden($VariabelID_Ab, false);
					//IPS_SetHidden($VariabelID_An, false);
					$sws_abw = false;
					$this->AbwesenheitsAuswahl();
				}
           		}
        }
        /**
        * Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
        * Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wiefolgt zur Verfügung gestellt:
        *
        * ABC_Calculate($id);
        *
        */
	
	public function RequestAction($key, $value){
		global $sws, $zp_conf, $sws_abw, $abw, $prog, $sw, $sw_abs, $sws_abw;
        	switch ($key) {
        		case 'SWS':
				$sws = $value;
				$zp_conf = getValue($this->GetIDForIdent("ZP_Conf"));
				$sws_abw = getValue($this->GetIDForIdent("SWS_Abw"));
				$abw = getValue($this->GetIDForIdent("Abw"));
				$this->ProgrammAuswahl();
            		break;
				
        		case 'prog':
				$prog = $value;
				$sw = getValue($this->GetIDForIdent("SW"));
				$sw_abs = getValue($this->GetIDForIdent("SW_Abs"));
				$this->SWRegler();
			break;
				
        		case 'SW':
				$prog = getValue($this->GetIDForIdent("prog"));
				$sw = $value;
				$sw_abs = getValue($this->GetIDForIdent("SW_Abs"));
				$this->SWRegler();
			break;
				
        		case 'SW_Abs':
				$prog = getValue($this->GetIDForIdent("prog"));
				$sw = getValue($this->GetIDForIdent("SW"));
				$sw_abs = $value;
				$this->SWRegler();
			break;
        		case 'SWS_Abw':
				$sws = getValue($this->GetIDForIdent("SWS"));
				$zp_conf = getValue($this->GetIDForIdent("ZP_Conf"));
				$sws_abw = $value;
				$abw = getValue($this->GetIDForIdent("Abw"));
				$this->AbwesenheitsAuswahl();				
			break;
        	}
		
        $this->SetValue($key, $value);	
		
   	}
	
	public function UpdateWeatherData(){
		
		$apikey = $this->ReadPropertyString("APIkey");
		$lat = $this->ReadPropertyFloat("Lat");
		$long = $this->ReadPropertyFloat("Long");
		
			$url = "https://api.darksky.net/forecast/$apikey/$lat,$long?exclude=currently,minutely&lang=de&units=ca";	
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			curl_setopt($ch, CURLOPT_URL, $url); 
			$output = curl_exec($ch);
			curl_close($ch);  
			$content = json_decode($output, true);
		
		SetValue($this->GetIDForIdent("AT"), $content['hourly']['data'][0]['temperature']);
		SetValue($this->GetIDForIdent("AT_2h"),$content['hourly']['data'][2]['temperature']);
		SetValue($this->GetIDForIdent("AT_4h"),$content['hourly']['data'][4]['temperature']);
		SetValue($this->GetIDForIdent("AT_8h"),$content['hourly']['data'][8]['temperature']);
		
		if ($this->ReadPropertyBoolean("WetterForcast")){
			IPS_SetHidden($this->GetIDForIdent("AT_2h"), false);
			IPS_SetHidden($this->GetIDForIdent("AT_4h"), false);
			IPS_SetHidden($this->GetIDForIdent("AT_8h"), false);
		}
		else{
			IPS_SetHidden($this->GetIDForIdent("AT_2h"), true);
			IPS_SetHidden($this->GetIDForIdent("AT_4h"), true);
			IPS_SetHidden($this->GetIDForIdent("AT_8h"), true);
		}
		
		
		//$prog = getValue($this->GetIDForIdent("prog"));
		//$sw = getValue($this->GetIDForIdent("SW"));
		//$sw_abs = getValue($this->GetIDForIdent("SW_Abs"));
		//$this->SWRegler();
	
	}
	
	
	public function VariabelStandartaktion(){
		
		$this->EnableAction("SWS");
		$this->EnableAction("prog");
		$this->EnableAction("SW");
		$this->EnableAction("SW_Abs");
		$this->EnableAction("SWS_Abw");
		
	}
		
	public function ZeitPro(){
		
	
		$KategorieID_Heizung = IPS_GetCategoryIDByName("Heizung", 0);
		$KategorieID_Settings = IPS_GetCategoryIDByName("Einstellungen", $KategorieID_Heizung);
		$InstanzID = IPS_GetInstanceIDByName("Regler", $KategorieID_Settings);
			
		$EreignisID =IPS_CreateEvent(2);
		IPS_SetName($EreignisID, "Zeitschaltprogramm");
		IPS_SetParent($EreignisID, $InstanzID);
		IPS_SetPosition($EreignisID, 10);
			
		IPS_SetEventScheduleGroup($EreignisID, 0, 31); //Mo - Fr (1 + 2 + 4 + 8 + 16)
		IPS_SetEventScheduleGroup($EreignisID, 1, 96); //Sa + So (32 + 64)
			
		IPS_SetEventScheduleAction($EreignisID, 0, "Eco", 0xFF8080, "SetValue(36402, false);");
		IPS_SetEventScheduleAction($EreignisID, 1, "Confort", 0xFF0000, "SetValue(36402, true);");
		
		IPS_SetEventScheduleGroupPoint($EreignisID, 0, 0, 0, 0, 0, 0); //Um 0:00 Aktion mit ID 0 "Eco" aufrufen
		IPS_SetEventScheduleGroupPoint($EreignisID, 0, 1, 6, 0, 0, 1); //Um 6:00 Aktion mit ID 1 "Comfort" aufrufen
		IPS_SetEventScheduleGroupPoint($EreignisID, 0, 2, 8, 0, 0, 0); //Um 8:00 Aktion mit ID 0 "Eco" aufrufen
		IPS_SetEventScheduleGroupPoint($EreignisID, 0, 3, 16, 0, 0, 1); //Um 16:00 Aktion mit ID 1 "Comfort" aufrufen
		IPS_SetEventScheduleGroupPoint($EreignisID, 0, 4, 22, 0, 0, 0); //Um 22:00 Aktion mit ID 0 "Eco" aufrufen
		
		IPS_SetEventScheduleGroupPoint($EreignisID, 1, 10, 0, 0, 0, 0); //Um 0:00 Aktion mit ID 0 "Eco" aufrufen
		IPS_SetEventScheduleGroupPoint($EreignisID, 1, 11, 7, 0, 0, 1); //Um 7:00 Aktion mit ID 1 "Comfort" aufrufen
		IPS_SetEventScheduleGroupPoint($EreignisID, 1, 12, 22, 0, 0, 0); //Um 22:00 Aktion mit ID 0 "Eco" aufrufen
		
		
		$EreignisID =IPS_CreateEvent(1);
		IPS_SetName($EreignisID, "Von");
		IPS_SetParent($EreignisID, $InstanzID);
		IPS_SetPosition($EreignisID, 13);
		IPS_SetEventCyclic($EreignisID, 1 /* Täglich */ ,5,0,0,0,0);
		
		$EreignisID_02 =IPS_CreateEvent(1);
		IPS_SetName($EreignisID_02, "Bis");
		IPS_SetParent($EreignisID_02, $InstanzID);
		IPS_SetPosition($EreignisID_02, 14);
		IPS_SetEventCyclic($EreignisID_02, 1 /* Täglich */ ,5,0,0,0,0);
	
		IPS_SetHidden($this->GetIDForIdent("ZP_Conf"), true);
		IPS_SetHidden($this->GetIDForIdent("Abw"), true);
		
	}
	
	public function AbwesenheitsAuswahl(){
		
		global $sws_abw;
		
		$KategorieID_Heizung = IPS_GetCategoryIDByName("Heizung", 0);
		$KategorieID_Settings = IPS_GetCategoryIDByName("Einstellungen", $KategorieID_Heizung);
		$InstanzID = IPS_GetInstanceIDByName("Regler", $KategorieID_Settings);
		$VariabelID_Ab = IPS_GetEventIDByName("Von", $InstanzID);
		$VariabelID_An = IPS_GetEventIDByName("Bis", $InstanzID);
		
				
		if($sws_abw == true){
			IPS_SetHidden($VariabelID_Ab, false);
			IPS_SetHidden($VariabelID_An, false);
		}
		else{
			IPS_SetHidden($VariabelID_Ab, true);
			IPS_SetHidden($VariabelID_An, true);
			$this->ProgrammAuswahl();
		}
	}
		
	public function ProgrammAuswahl(){
		
		global $sws, $zp_conf, $sws_abw, $abw;
		//$test = getValue($this->GetIDForIdent("SWS_Abw"));
		
		if($sws == 0){
			SetValue($this->GetIDForIdent("prog"), 0);
			IPS_SetDisabled($this->GetIDForIdent("prog"), false);
			//echo "Aus";
		}
		else if($sws == 1){
			IPS_SetDisabled($this->GetIDForIdent("prog"), false);
			//echo "Hand";
		}
		else{
			IPS_SetDisabled($this->GetIDForIdent("prog"), true);
			//echo "Auto";
			
			if($abw == true && $sws_abw == true){
				SetValue($this->GetIDForIdent("prog"), 3);
				IPS_SetDisabled($this->GetIDForIdent("prog"), true);
				//echo "Abwesend";
			}
			else if($zp_conf == false){
				SetValue($this->GetIDForIdent("prog"), 1);
				IPS_SetDisabled($this->GetIDForIdent("prog"), true);
				//echo "Eco";
			}
			else{
				SetValue($this->GetIDForIdent("prog"), 2);
				IPS_SetDisabled($this->GetIDForIdent("prog"), true);
				//echo "Confort";
			}
		}
		
			
		if($abw == false){
			SetValue($this->GetIDForIdent("SWS_Abw"), false);
		}
	}
	
	public function SWRegler(){
		global $prog, $sw, $sw_abs;
		$program = $prog;
		$sollwert = $sw;
		$sollwert_ab = $sw_abs;
		
		$AT = $this->getValue("AT");
		$AT_2 = $this->getValue("AT_2h");
		$AT_4 = $this->getValue("AT_4h");
		$AT_8 = $this->getValue("AT_8h");
			
			//_________________Heizung_Eco__________________________________________________
			if ($program == 0) {
				$sollwert_ber = 0;
				SetValue($this->GetIDForIdent("SB"), 0);
				IPS_SetHidden($this->GetIDForIdent("SB"), true);
                	} 
			//_________________Heizung_Eco__________________________________________________
			if ($program == 1) {
				$sollwert_ber = ($sollwert - $sollwert_ab);
				SetValue($this->GetIDForIdent("SB"), 0);
				IPS_SetHidden($this->GetIDForIdent("SB"), true);
                	} 
			//_________________Heizung_Comfort______________________________________________ 
			else if ($program == 2) {
				//___Modul_Übergangszeit____________________________________________________
				if ($AT >= $sollwert || $AT_2 >= $sollwert || $AT_4 >= $sollwert || $AT_8 >= $sollwert){
					$sollwert_ber = ($sollwert - 2);
					SetValue($this->GetIDForIdent("SB"), 1);
					IPS_SetHidden($this->GetIDForIdent("SB"), false);
				}
				//___Modul_Frost____________________________________________________________
				else if ($AT <= -5){
					$sollwert_ber = $sollwert - (($AT + 5) * (-0.1));  //Schiebung über Vareabeln (((15-5)/100) * -1)
					SetValue($this->GetIDForIdent("SB"), 2);
					IPS_SetHidden($this->GetIDForIdent("SB"), false);
				}
				//___Modul_Comfort__________________________________________________________
				else{
					$sollwert_ber = ($sollwert);
					SetValue($this->GetIDForIdent("SB"), 0);
					IPS_SetHidden($this->GetIDForIdent("SB"), true);
				}
			}
			//_________________Heizung_Abwesend_____________________________________________ 
			else if ($program == 3) {
				$sollwert_ber = 18;
				SetValue($this->GetIDForIdent("SB"), 0);
				IPS_SetHidden($this->GetIDForIdent("SB"), true);
			}
		
		SetValue($this->GetIDForIdent("SW_ber"), $sollwert_ber);
         
	}
	
	public function Test(){
		
		$this->EnableAction("SW_Abs");
		
		
	}
	
    
		   
    }
?>