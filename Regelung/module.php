<?
	
class HeizungssteuerungRegler extends IPSModule
	{
		
		public function Create()
		{
			//Never delete this line!
			parent::Create();
			
			//___In_IPS_zurverfügungstehende_Variabeln_______________________________________________
			$this->RegisterVariableFloat("RT", "Raumtemperatur", "~Temperature.Room", 1);
			$this->RegisterVariableFloat("SW_Ra", "Sollwert", "~Temperature.Room", 2);
			$this->RegisterVariableFloat("SW_Anp", "Sollwert Anpassung", "Heizung_Abs", 3);

			//___Modulvariabeln______________________________________________________________________
			//$this->RegisterPropertyInteger("SWS", 1);
			//$this->RegisterPropertyBoolean("ZP_Conf", true);
			//$this->RegisterPropertyInteger("Test", 0);
			//$this->RegisterPropertyInteger("prog", 1);
			//$this->RegisterPropertyFloat("SW", 15);
			//$this->RegisterPropertyFloat("SW_Abs", 3);
			
			//$this->RegisterPropertyBoolean("Abw", true);
			
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
			
			
			//Standartaktion Aktivieren
			//$this->VariabelStandartaktion();
			
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
	
	
	public function VariabelStandartaktion(){
		
		//$this->EnableAction("SWS");
		
	}
		
	public function TrendDiagramm(){
		
	
			
	}
	
	public function Regler(){
		//global $prog, $sw, $sw_abs;

			
		$Ist_RT = getValue("RT");
		$Sollwert = getValue("SW");
		$Sollwert_KOR_RA = getValue("SW_Ra");
		$Programm = getValue("prog");

		$Histerese_aus = -0.0; // Histerese um bei 0.0K vor Sollwert den Stellantrieb zu schliessen (Stand 09.12.18)
		$Histerese_ein = -0.5; // Histerese um bei 0.5K vor Sollwert den Stellantrieb zu öffnen (Stand 09.12.18)

		//___Regelung_Abwesend____________________________________________________________________________________________________________

		if($Programm == 3){
			SetValue("SW_Ra", 18);													// Raumsollwert für Anzeige
			if((18 + $Histerese_aus) <= $Ist_RT){
				//ZW_SwitchMode(48378, false);
			}
			elseif((18 + $Histerese_ein) >= $Ist_RT){
				//ZW_SwitchMode(48378, true);
			}
		}

		//___Regelung_Normalzustand_______________________________________________________________________________________________________

		else{
        	SetValue("SW_Ra", ($Sollwert_ber + $Sollwert_KOR_RA));					// Raumsollwert für Anzeige
	    		if($Programm <= 3 and (($Sollwert_ber + $Sollwert_KOR_RA + $Histerese_aus) <= $Ist_RT)){
		    		//ZW_SwitchMode(48378, false);
	    	}

        	elseif(($Sollwert_ber + $Sollwert_KOR_RA + $Histerese_ein) >= $Ist_RT){
			//ZW_SwitchMode(48378, true);
	    	}
		
		//SetValue($this->GetIDForIdent("SW_ber"), $sollwert_ber);
         
	}
	
		
	public function Test(){
		
		$this->EnableAction("SW_Abs");
		
		
	}
	
    
		   
    }
?>
