<?

$sw_ra_anp = 0;

class HeizungssteuerungRaum extends IPSModule
	{
		
		public function Create()
		{
			//Never delete this line!
			parent::Create();
			
			//___In_IPS_zurverfügungstehende_Variabeln_______________________________________________
			$this->RegisterVariableFloat("RT", "Raumtemperatur", "~Temperature.Room", 1);
			$this->RegisterVariableFloat("SW_Ra", "Sollwert", "~Temperature.Room", 2);
			$this->RegisterVariableFloat("SW_Anp", "Sollwert Anpassung", "Heizung_Abs", 3);
			
			$this->RegisterVariableBoolean("Ventil", "Ventil", "~Switch", 10);

			//___Modulvariabeln______________________________________________________________________
			$this->RegisterPropertyInteger("TrigSollwert", 0);
			$this->RegisterPropertyInteger("TrigRaumtemp", 0);
			
			$this->RegisterPropertyBoolean("AktivierungRT", true);
			$this->RegisterPropertyBoolean("AktivierungVentil", true);
			
			$this->RegisterPropertyInteger("V_An_01", true);
			$this->RegisterPropertyInteger("V_An_02", true);
			$this->RegisterPropertyInteger("V_An_03", true);
			$this->RegisterPropertyInteger("SW_An", true);
		}
	
	        public function ApplyChanges() {
            		//Never delete this line!
            		parent::ApplyChanges();
			
				
            		$triggerIDSW = $this->ReadPropertyInteger("TrigSollwert");
            		$this->RegisterMessage($triggerIDSW, 10603 /* VM_UPDATE */);
			
			$triggerIDRT = $this->ReadPropertyInteger("TrigRaumtemp");
			$this->RegisterMessage($triggerIDRT, 10603 /* VM_UPDATE */);
					
			//Standartaktion Aktivieren
			$this->VariabelStandartaktion();
			
        	}
	
	        public function MessageSink ($TimeStamp, $SenderID, $Message, $Data) {
		global $rt, $sw_ra, $sw_ra_anp;
            		$triggerIDSW = $this->ReadPropertyInteger("TrigSollwert");
			$triggerIDRT = $this->ReadPropertyInteger("TrigRaumtemp");
	
			if (($SenderID == $triggerIDSW) && ($Message == 10603)){// && (boolval($Data[0]))){
				$sw_ra_anp = getValue($this->GetIDForIdent("SW_Anp"));
				$this->Regler();
           		}
			if (($SenderID == $triggerIDRT) && ($Message == 10603)){// && (boolval($Data[0]))){
				$sw_ra_anp = getValue($this->GetIDForIdent("SW_Anp"));
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
		global $sw_ra_anp;
        	switch ($key) {
        		case 'SW_Anp':
				$sw_ra_anp = $value;
				$this->Regler();
            		break;
        	}
		
        $this->SetValue($key, $value);	
		
   	}
	
	
	public function VariabelStandartaktion(){
		$this->EnableAction("SW_Anp");
		
		if ($this->ReadPropertyBoolean("AktivierungRT")){
			IPS_SetHidden($this->GetIDForIdent("RT"), false);
		}
		else{
			IPS_SetHidden($this->GetIDForIdent("RT"), true);
		}
		
		if ($this->ReadPropertyBoolean("AktivierungVentil")){
			IPS_SetHidden($this->GetIDForIdent("Ventil"), false);
		}
		else{
			IPS_SetHidden($this->GetIDForIdent("Ventil"), true);
		}
	}
		
	public function TrendDiagramm(){
					
	}
	
	public function Regler(){
		global $sw_ra_anp;
		
		$KategorieID_Heizung = IPS_GetCategoryIDByName("Heizung", 0);
		$KategorieID_Settings = IPS_GetCategoryIDByName("Einstellungen", $KategorieID_Heizung);
		$InstanzID = IPS_GetInstanceIDByName("Regler", $KategorieID_Settings);
		
		$rt = getValue($this->ReadPropertyInteger("TrigRaumtemp"));
		$sw_regler =  getValue(IPS_GetVariableIDByName("Sollwert Berechnet", $InstanzID));
		$programm =  getValue(IPS_GetVariableIDByName("Programm", $InstanzID));

		$Histerese_aus = -0.0; // Histerese um bei 0.0K vor Sollwert den Stellantrieb zu schliessen (Stand 09.12.18)
		$Histerese_ein = -0.5; // Histerese um bei 0.5K vor Sollwert den Stellantrieb zu öffnen (Stand 09.12.18)

		//___Regelung_Abwesend____________________________________________________________________________________________________________

		if($programm == 3){
			SetValue($this->GetIDForIdent("SW_Ra"), 18);													// Raumsollwert für Anzeige
			
			if (IPS_InstanceExists($this->ReadPropertyInteger("SW_An"))) {
				ZW_ThermostatSetPointSet($this->ReadPropertyInteger("SW_An"), 1, 18);
			}
			if((18 + $Histerese_aus) <= $rt){
				SetValue($this->GetIDForIdent("Ventil"), false);
				
				if (IPS_VariableExists($this->ReadPropertyInteger("V_An_01"))) {
					SetValue($this->ReadPropertyInteger("V_An_01"), false);
				}				
				if (IPS_VariableExists($this->ReadPropertyInteger("V_An_02"))) {
					SetValue($this->ReadPropertyInteger("V_An_02"), false);
				}
				if (IPS_VariableExists($this->ReadPropertyInteger("V_An_03"))) {
					SetValue($this->ReadPropertyInteger("V_An_03"), false);
				}
			}
			else if((18 + $Histerese_ein) >= $rt){
				SetValue($this->GetIDForIdent("Ventil"), true);
				
				if (IPS_VariableExists($this->ReadPropertyInteger("V_An_01"))) {
					SetValue($this->ReadPropertyInteger("V_An_01"), true);
				}
				if (IPS_VariableExists($this->ReadPropertyInteger("V_An_02"))) {
					SetValue($this->ReadPropertyInteger("V_An_02"), true);
				}
				if (IPS_VariableExists($this->ReadPropertyInteger("V_An_03"))) {
					SetValue($this->ReadPropertyInteger("V_An_03"), true);
				}
			}
		}

		//___Regelung_Normalzustand_______________________________________________________________________________________________________

		else{
        	SetValue($this->GetIDForIdent("SW_Ra"), ($sw_regler + $sw_ra_anp));					// Raumsollwert für Anzeige
		//SetValue($this->ReadPropertyInteger("SW_An"), ($sw_regler + $sw_ra_anp));
		//ZW_ThermostatSetPointSet("SW_An", 1, 20);
		if (IPS_InstanceExists($this->ReadPropertyInteger("SW_An"))) {
			ZW_ThermostatSetPointSet($this->ReadPropertyInteger("SW_An"), 1, ($sw_regler + $sw_ra_anp));
		}
			
	    		if($programm <= 3 and (($sw_regler + $sw_ra_anp + $Histerese_aus) <= $rt)){
		    		SetValue($this->GetIDForIdent("Ventil"), false);
				
				if (IPS_VariableExists($this->ReadPropertyInteger("V_An_01"))) {
					SetValue($this->ReadPropertyInteger("V_An_01"), false);
				}
				if (IPS_VariableExists($this->ReadPropertyInteger("V_An_02"))) {
					SetValue($this->ReadPropertyInteger("V_An_02"), false);
				}
				if (IPS_VariableExists($this->ReadPropertyInteger("V_An_03"))) {
					SetValue($this->ReadPropertyInteger("V_An_03"), false);
				}
	    		}
		
        		else if(($sw_regler + $sw_ra_anp + $Histerese_ein) >= $rt){
				SetValue($this->GetIDForIdent("Ventil"), true);
				
				if (IPS_VariableExists($this->ReadPropertyInteger("V_An_01"))) {
					SetValue($this->ReadPropertyInteger("V_An_01"), true);
				}
				if (IPS_VariableExists($this->ReadPropertyInteger("V_An_02"))) {
					SetValue($this->ReadPropertyInteger("V_An_02"), true);
				}
				if (IPS_VariableExists($this->ReadPropertyInteger("V_An_03"))) {
					SetValue($this->ReadPropertyInteger("V_An_03"), true);
				}
	    		}
		}
		
		SetValue($this->GetIDForIdent("RT"), $rt);
         
	}
	
		
	public function Test(){
		//$KategorieID_Heizung = IPS_GetCategoryIDByName("Heizung", 0);
		//$KategorieID_Settings = IPS_GetCategoryIDByName("Einstellungen", $KategorieID_Heizung);
		//$InstanzID = IPS_GetInstanceIDByName("Regler", $KategorieID_Settings);
		
		//SetValue($this->GetIDForIdent("SW_Ra"), getValue(IPS_GetVariableIDByName("Sollwert Berechnet", $InstanzID)));
		
		//$Ist_RT = getValue($this->ReadPropertyInteger("TrigRaumtemp"));
		
		//SetValue($this->GetIDForIdent("RT"), $Ist_RT);
		
		//$this->EnableAction("SW_Anp");
	}
	
    
		   
    }
?>
