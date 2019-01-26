<?

$rt = 20;
$sw_ra = 22;
$sw_anp = 0;

class HeizungssteuerungRaum extends IPSModule
	{
		
		public function Create()
		{
			//Never delete this line!
			parent::Create();
			
			//___In_IPS_zurverfügungstehende_Variabeln_______________________________________________
			$this->RegisterVariableFloat("RT", "Raumtemperatur", "~Temperature.Room", 1);
			$this->RegisterVariableFloat("SW_Ra", "Sollwert", "~Temperature.Room", 2);
			$this->RegisterVariableFloat("SW_Anp", "Sollwert Anpassung", "~Temperature.Room", 3);
			
			$this->RegisterVariableBoolean("Ventil", "Ventil", "~Switch", 10);

			//___Modulvariabeln______________________________________________________________________
			$this->RegisterPropertyInteger("TrigSollwert", 0);
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
			
				
            		$triggerIDSW = $this->ReadPropertyInteger("TrigSollwert");
            		$this->RegisterMessage($triggerIDSW, 10603 /* VM_UPDATE */);
			
			//$triggerIDConf = $this->ReadPropertyInteger("TrigConfort");
			//$this->RegisterMessage($triggerIDConf, 10603 /* VM_UPDATE */);			
			
			//Standartaktion Aktivieren
			//$this->VariabelStandartaktion();
			
        	}
	
	        public function MessageSink ($TimeStamp, $SenderID, $Message, $Data) {
		global $rt, $sw_ra, $sw_anp;
            		$triggerIDSW = $this->ReadPropertyInteger("TrigProgramm");
			//$triggerIDConf = $this->ReadPropertyInteger("TrigConfort");
	
			if (($SenderID == $triggerIDSW) && ($Message == 10603)){// && (boolval($Data[0]))){
				$rt = getValue($this->GetIDForIdent("RT"));
				$sw_ra = getValue($this->GetIDForIdent("SW_Ra"));
				$sw_anp = getValue($this->GetIDForIdent("SW_Anp"));
				$this->Regler();
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
		global $rt, $sw_ra, $sw_anp;
        	switch ($key) {
        		case 'SW_Anp':
				$rt = getValue($this->GetIDForIdent("RT"));
				$sw_ra = getValue($this->GetIDForIdent("SW_Ra"));
				$sw_anp = $value;
				$sws = $value;
				$this->Regler();
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
		$Sollwert_ber = getValue("SW");
		$Sollwert_KOR_RA = getValue("SW_Ra");
		$Programm = getValue("prog");

		$Histerese_aus = -0.0; // Histerese um bei 0.0K vor Sollwert den Stellantrieb zu schliessen (Stand 09.12.18)
		$Histerese_ein = -0.5; // Histerese um bei 0.5K vor Sollwert den Stellantrieb zu öffnen (Stand 09.12.18)

		//___Regelung_Abwesend____________________________________________________________________________________________________________

		if($Programm == 3){
			SetValue("SW_Ra", 18);													// Raumsollwert für Anzeige
			if((18 + $Histerese_aus) <= $Ist_RT){
				SetValue("Ventil", false);
			}
			else if((18 + $Histerese_ein) >= $Ist_RT){
				SetValue("Ventil", true);
			}
		}

		//___Regelung_Normalzustand_______________________________________________________________________________________________________

		else{
        	SetValue("SW_Ra", ($Sollwert_ber + $Sollwert_KOR_RA));					// Raumsollwert für Anzeige
			
	    		if($Programm <= 3 and (($Sollwert_ber + $Sollwert_KOR_RA + $Histerese_aus) <= $Ist_RT)){
		    		SetValue("Ventil", false);
	    		}
		
        		else if(($Sollwert_ber + $Sollwert_KOR_RA + $Histerese_ein) >= $Ist_RT){
				SetValue("Ventil", true);
	    		}
		}
		
		//SetValue($this->GetIDForIdent("SW_ber"), $sollwert_ber);
         
	}
	
		
	public function Test(){
		
		
		$KategorieID_Heizung = IPS_GetCategoryIDByName("Heizung", 0);
		$KategorieID_Settings = IPS_GetCategoryIDByName("Einstellungen", $KategorieID_Heizung);
		$InstanzID = IPS_GetInstanceIDByName("Regler", $KategorieID_Settings);
		
		$SW = IPS_GetVariableIDByName("Sollwert", $InstanzID);
		//$SW = getValue($this->IPS_GetVariableIDByName("Sollwert", $InstanzID));
		//getValue($this->IPS_GetVariableIDByName("Sollwert Berechnet", $InstanzID))
			
		SetValue($this->GetIDForIdent("SW_Ra"), $SW);
		
		//$this->EnableAction("SW_Abs");
		
		
	}
	
    
		   
    }
?>
