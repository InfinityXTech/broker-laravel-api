<?php

namespace App\Classes\Report;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ReportFormulas {
    protected $start, $end;

    public function __construct($start, $end)
    {
        $this->start = $start;
        $this->end = $end;
    }

    protected function formulas () {
        return [
            'fraudLowRisk' => [
                'fraud_low_risk' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(string)riskScale__ == "Low Risk" ) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'fraudMediumRisk' => [
                'fraud_medium_risk' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(string)riskScale__ == "Medium Risk" ) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'fraudHighRisk' => [
                'fraud_high_risk' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(string)riskScale__ == "High Risk" || __(string)riskScale__ == "Very High Risk" ) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'redirect' => [
                'hit_the_redirect' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)hit_the_redirect__ == TRUE ) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'mismatch' => [
                'mismatch_leads' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __match_with_broker__ == 0 && __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'all_leads' => [
                'all_leads' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'master_brand_payout' => [
                'master_brand_payout' =>  [
                    'type' => 'sum',
                    'field_type' => 'float',
                    'formula' => '
                        $cost = 0.0;
                        $crg = 0.0;
                        if ( __(bool)match_with_broker__ == FALSE ) {
                            $cost = 0;
                        }elseif( __(bool)broker_cpl__ == TRUE) {
                            if(__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ){
                                $cost = __Master_brand_cost__;
                            }
                        }else{
                            
                            if ( __(bool)broker_crg_deal__ == TRUE && __broker_crg_master_revenue__ > 0 &&__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                                $crg = __broker_crg_master_revenue__;
                            }								
                            
                            if ( __(bool)depositor__ == TRUE && __depositTimestamp__ >= ' . $this->start . ' && __depositTimestamp__ <= ' . $this->end . ' ) {
                                // && __(bool)deposit_disapproved__ == FALSE									
                                //approved  FTD
                                
                                $cost = $crg;
                                
                                if ((__(bool)broker_crg_ftd_uncount__ == TRUE || __(bool)broker_crg_already_paid__ == TRUE) && __(bool)broker_crg_deal__ == TRUE){
                                    
                                }else if(' .
                        // TODO: Access
                        // (customUserAccess::is_forbidden('deposit_disapproved') 
                        (Gate::has('custom[deposit_disapproved]') && Gate::allows('custom[deposit_disapproved]') ? ' __(bool)deposit_disapproved__ == FALSE ' : 'true') . '){
                                        $cost = $cost + __master_brand_payout__;
                                    }								
                                
                            }else{
                                $cost = $crg;
                            }
                            
                        }
                        return (float)$cost;
                    ',
                    'formula_return' => false
                ]
            ],
            'master_affiliate_payout' => [
                'master_affiliate_payout' => [
                    'type' => 'sum',
                    'field_type' => 'float',
                    'formula' => '
                        $cost = 0.0;
                        $crg = 0.0;
                        if ( __(bool)match_with_broker__ == FALSE ) {
                            $cost = 0;
                        }elseif( __(bool)isCPL__ == TRUE) {
                            if(__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ){
                                $cost = __Mastercost__;
                            }
                        }else{
                            
                            if ( __(bool)crg_deal__ == TRUE && __crg_master_revenue__ > 0 &&__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                                $crg = __crg_master_revenue__;
                            }
                            
                            if (
                            ((__(bool)depositor__ == TRUE && __(bool)deposit_disapproved__ == FALSE) || __(bool)fakeDepositor__ == TRUE )
                            && __endpointDepositTimestamp__ >= ' . $this->start . ' && __endpointDepositTimestamp__ <= ' . $this->end . ' ) {
                                //approved  FTD
                                if ((__(bool)crg_ftd_uncount__ == TRUE || __(bool)crg_already_paid__ == TRUE) && __(bool)crg_deal__ == TRUE){
                                    $cost = $crg;
                                }else{
                                    $cost = $crg + __master_affiliate_payout__;								
                                }
                            }else{
                                $cost = $crg;
                            }
                            
                        }
                        return (float)$cost;
                    ',
                    'formula_return' => false
                ]
            ],
            'affiliate_cost' => [
                'affiliate_cost' =>  array(
                    'type' => 'sum',
                    'field_type' => 'float',
                    'formula' => '
                        $cost = 0.0;
                        $crg = 0.0;
                        if ( __(bool)match_with_broker__ == FALSE ) {
                            $cost = 0;
                        } elseif( __(bool)isCPL__ == TRUE) {
                            if(__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ){
                                $cost = __cost__;
                            }
                        }else{
                            
                            if ( __(bool)crg_deal__ == TRUE && __crg_revenue__ > 0 &&__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                                $crg = __crg_revenue__;
                            }
                            
                            if ( 
                            ((__(bool)depositor__ == TRUE && __(bool)deposit_disapproved__ == FALSE) || __(bool)fakeDepositor__ == TRUE ) 
                            && __endpointDepositTimestamp__ >= ' . $this->start . ' && __endpointDepositTimestamp__ <= ' . $this->end . ' ) {
                                //approved  FTD									
                                if ((__(bool)crg_ftd_uncount__ == TRUE || __(bool)crg_already_paid__ == TRUE ) && __(bool)crg_deal__ == TRUE){
                                    $cost = $crg;
                                }else{
                                    $cost = $crg + __cost__;								
                                }									
                            }else{
                                $cost = $crg;
                            }
                            
                        }
                        return round((float)$cost, 2);
                    ',
                    'formula_return' => false
                )
            ],
            'Leads' => [
                'Leads' => [
                    'type' => 'count',
                    'formula' => '
                            if ( __(bool)match_with_broker__ == TRUE && __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                                return true;
                            }
                            return false;
                        ',
                    'formula_return' => false
                ]
            ],
            'cost' => [
                'cost' => [
                    'type' => 'sum',
                    'field_type' => 'float',
                    'formula' => '
                        $cost = 0.0;                
                        if ( __(bool)match_with_broker__ == FALSE ) {
                            $cost = 0;
                        }elseif ( __(bool)isCPL__ == TRUE && __(bool)broker_cpl__ == TRUE){
                            if(__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ){
                                $cost = __cost__ + __Mastercost__ + __Master_brand_cost__;
                            }
                        }else{	
                            
                            //aff cpl
                            if ( __(bool)isCPL__ == TRUE &&__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ){
                                $cost = $cost + __cost__;
                            }
                            
                            //aff crg
                            if (__(bool)isCPL__ == FALSE && __(bool)crg_deal__ == TRUE && __crg_revenue__ > 0 &&__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                                $cost = $cost + __crg_revenue__;
                                
                                //master_aff crg
                                if(__crg_master_revenue__ > 0){
                                    $cost = $cost + __crg_master_revenue__;
                                }
                            }
                            
                            //master_aff CPL
                            if ( __(bool)isCPL__ == TRUE && __Mastercost__>0 &&__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ){
                                $cost = $cost + __Mastercost__;
                            }					
                            
                            // master_broker crg
                            if ( __(bool)broker_crg_deal__ == TRUE && __broker_crg_master_revenue__ > 0 &&__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                                $cost = $cost + __broker_crg_master_revenue__;						
                            }					
                            
                            // master_broker cpl
                            if (__(bool)broker_cpl__ == TRUE && __Master_brand_cost__>0 &&__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ){
                                $cost = $cost + __Master_brand_cost__;
                            }
                            
                            //FTD for master_broker
                            if ( __(bool)depositor__ == TRUE && __depositTimestamp__ >= ' . $this->start . ' && __depositTimestamp__ <= ' . $this->end . ' ) {
                                
                                if ((__(bool)broker_crg_ftd_uncount__ == TRUE || __(bool)broker_crg_already_paid__ == TRUE)&& __(bool)broker_crg_deal__ == TRUE){
                                    
                                }elseif(__(bool)broker_cpl__ == FALSE ' .
                        // TODO: Access
                        // (customUserAccess::is_forbidden('deposit_disapproved') 
                        (Gate::has('custom[deposit_disapproved]') && Gate::allows('custom[deposit_disapproved]') ? ' && __(bool)deposit_disapproved__ == FALSE ' : '') . '){
                                        $cost = $cost + __master_brand_payout__;
                                    }
        
                            }
        
                            //FTD for endpoints  AND master_affiliate
                            if (
                                ((__(bool)depositor__ == TRUE && __(bool)deposit_disapproved__ == FALSE) || __(bool)fakeDepositor__ == TRUE )
                                && __endpointDepositTimestamp__ >= ' . $this->start . ' && __endpointDepositTimestamp__ <= ' . $this->end . ' ) {
                                    
                                if ((__(bool)crg_ftd_uncount__ == TRUE || __(bool)crg_already_paid__ == TRUE) && __(bool)crg_deal__ == TRUE){
                                    
                                }elseif(__(bool)isCPL__ == FALSE){
                                    //approved
                                    $cost = $cost + __cost__ + __master_affiliate_payout__;
                                }						
                            }
                            
                        }
                        $cost = ((float)$cost) + __(float)adjustment_amount__;
                        return (float)$cost;
                    ',
                    'formula_return' => false
                ]
            ],
            'deposit_revenue' => [
                'deposit_revenue' =>  [
                    'type' => 'sum',
                    'field_type' => 'float',
                    'formula' => '
                            $revenue = 0.0;
                            $crg= 0.0;
                            if ( __(bool)match_with_broker__ == FALSE ) {
                                $revenue = 0;
                            } elseif( __(bool)broker_cpl__ == TRUE){
                                if(__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ){
                                    $revenue = __revenue__;
                                }
                            } else{
                                if ( __(bool)broker_crg_deal__ == TRUE && __broker_crg_revenue__ > 0 &&__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                                    $crg = __broker_crg_revenue__;
                                }
                                if ( __(bool)broker_crg_ftd_uncount__ == FALSE && __(bool)broker_crg_already_paid__ == FALSE && __(bool)depositor__ == TRUE && __depositTimestamp__ >= ' . $this->start . ' && __depositTimestamp__ <= ' . $this->end . ' ' .
                        // TODO: Access
                        // (customUserAccess::is_forbidden('deposit_disapproved') 
                        (Gate::has('custom[deposit_disapproved]') && Gate::allows('custom[deposit_disapproved]') ? ' && __(bool)deposit_disapproved__ == FALSE ' : ' ')
                        . ') {
                                        //&& __(bool)deposit_disapproved__ == FALSE
                                        $crg = $crg + __deposit_revenue__;
                                    }
                                $revenue = $crg;
                            }
                            return (float)$revenue;
                        ',
                    'formula_return' => false
                ]
            ],
            'ApprovedDepositors' => [
                'depositor' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)depositor__ == TRUE && __(bool)deposit_disapproved__ == FALSE && __depositTimestamp__ >= ' . $this->start . ' && __depositTimestamp__ <= ' . $this->end . ' ) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ],
                'approved_deposit' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)isCPL__ == TRUE && __(bool)broker_cpl__ == TRUE ) {
                            return false;
						} elseif ( __(bool)test_lead__ == TRUE) {
                            return false; 	
                        } elseif ( __(bool)depositor__ == TRUE && __(bool)deposit_disapproved__ == FALSE  && __endpointDepositTimestamp__ >= ' . $this->start . ' && __endpointDepositTimestamp__ <= ' . $this->end . ' ) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'valid_leads' => [
                'valid_leads' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)crg_deal__ == false && __(bool)test_lead__ == FALSE && __(bool)crg_ignored_by_status__ == TRUE && __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                            return false;
                        }
                        return true;
                    ',
                    'formula_return' => false
                ]
            ],
            'Depositors' => [
                'depositor' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)isCPL__ == TRUE && __(bool)broker_cpl__ == TRUE ) {
                            return false;
                        } elseif ( __(bool)test_lead__ == TRUE) {
                            return false;                        
                        } elseif ( 
                            __(bool)depositor__ == TRUE && 
                            ' .
                        // TODO: Access
                        // (customUserAccess::is_forbidden('deposit_disapproved') 
                        (Gate::has('custom[deposit_disapproved]') && Gate::allows('custom[deposit_disapproved]') ? ' __(bool)deposit_disapproved__ == FALSE && ' : '') .
                        '__depositTimestamp__ >= ' . $this->start . ' && __depositTimestamp__ <= ' . $this->end . ' ) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'broker_cpl_leads' => [
                'broker_cpl_leads' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)broker_cpl__ == TRUE && __(bool)match_with_broker__ == TRUE) {
                            // && __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . '
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'cpl_leads' => [
                'cpl_leads' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)isCPL__ == TRUE && __(bool)match_with_broker__ == TRUE) {
                            // && __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . '
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'broker_crg_leads' => [
                'broker_crg_leads' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)broker_crg_deal__ == TRUE) {
                            // && __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . '
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'crg_leads' => [
                'crg_leads' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)crg_deal__ == TRUE) {
                            // && __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . '
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'test_lead' => [
                'test_lead' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)test_lead__ == TRUE) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'broker_crg_already_paid_ftd' => [
                'broker_crg_already_paid_ftd' => [
                    'type' => 'count',
                    'formula' => '
                        if (__(bool)broker_cpl__ == TRUE ) {
                            return false;
                        } elseif (__(bool)broker_crg_deal__ == TRUE && __(bool)broker_crg_already_paid__ == TRUE && __(bool)depositor__ == TRUE && __depositTimestamp__ >= ' . $this->start . ' && __depositTimestamp__ <= ' . $this->end . ' ) {
                            // && __(bool)deposit_disapproved__ == FALSE
							return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'crg_already_paid_ftd' => [
                'crg_already_paid_ftd' => [
                    'type' => 'count',
                    'formula' => '
                        if (__(bool)isCPL__ == TRUE) {
                            return false;
                        } elseif (__(bool)crg_deal__ == TRUE && __(bool)crg_already_paid__ == TRUE 
						&&((__(bool)depositor__ == TRUE && __(bool)deposit_disapproved__ == FALSE) || __(bool)fakeDepositor__ == TRUE )
						&& __endpointDepositTimestamp__ >= ' . $this->start . ' && __endpointDepositTimestamp__ <= ' . $this->end . ' ) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'fake_FTD' => [
                'fake_FTD' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)test_lead__ == TRUE) {
                            return false; 	
                        } elseif ( __(bool)fakeDepositor__ == TRUE && __endpointDepositTimestamp__ >= ' . $this->start . ' && __endpointDepositTimestamp__ <= ' . $this->end . ' ) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'test_FTD' => [
                'test_FTD' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)depositor__ == TRUE && __(bool)test_lead__ == TRUE  && __depositTimestamp__ >= ' . $this->start . ' && __depositTimestamp__ <= ' . $this->end . ' ) {
                            return true;
                        }else if ( __(bool)fakeDepositor__ == TRUE && __(bool)test_lead__ == TRUE  && __endpointDepositTimestamp__ >= ' . $this->start . ' && __endpointDepositTimestamp__ <= ' . $this->end . ' ) {
							 return true;
						}
                        return false;
                    ',
                    'formula_return' => false
                ]
            ],
            'BlockedLeads' => [
                'BlockedLeads' => [
                    'type' => 'count',
                    'where' => 'match_with_broker',
                    'value' => false
                ]
            ],
            'adjustment_amount' => [
                'adjustment_amount' => [
                    'type' => 'sum',
                    'field_type' => 'float',
                    'formula' => '
                            return __adjustment_amount__;
                        ',
                    'formula_return' => false
                ]
            ]
        ];
    }

    public function attach ($formulas, &$array) {
        if (is_array($formulas)) {
            foreach ($formulas as $formula) {
                if (!isset($array[$formula])) {
                    $this->setFormula($formula, $array);
                }
            }
        } else {
            if (!isset($array[$formulas])) {
                $this->setFormula($formulas, $array);
            }
        }
    }

    /**
     * In case of more formulas within same "key"
     */
    protected function setFormula ($formula, &$array) {
        $key = str($formula)->contains('.') ? str($formula)->before('.')->value : $formula;
        $array[$key] = data_get($this->formulas(), $formula);
    }
}