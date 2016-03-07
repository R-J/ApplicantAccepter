<?php defined('APPLICATION') or die;

$PluginInfo['ApplicantAccepter'] = array(
    'Name' => 'Applicant Accepter',
    'Description' => 'Adds information to user table who accepted an applicant.',
    'Version' => '0.1',
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'SettingsUrl' => '/dashboard/settings/applicantaccepter',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'Author' => 'Robin Jurinka',
    'AuthorUrl' => 'http://www.vanillaforums.org/profile/r_j',
    'License' => 'MIT'
);

class ApplicantAccepterPlugin extends Gdn_Plugin {

    private $_InsertUserID = 0;
    
    // called when plugin is enabled
    public function setup() {
        // init config settings
        if (!C('Plugins.ApplicantAccepter.ApplicantRoleID')) {
            SaveToConfig('Plugins.ApplicantAccepter.ApplicantRoleID', 4);
        }
        if (!C('Plugins.ApplicantAccepter.ShowDiscoveryText')) {
            SaveToConfig('Plugins.ApplicantAccepter.ShowDiscoveryText', true);
        }
    
        // change user table
        $this->structure();
    }

    // called by setup, adds column to user table
    public function structure() {
        $Database = Gdn::Database();
        $Structure = $Database->Structure();
        $Structure
            ->Table('User')
            ->Column('ApplicantAccepter', 'int(11)', 0)
            ->Set();
    }
    
    // very basic settings screen
    public function settingsController_applicantAccepter_create ($Sender) {
        $Sender->Permission('Garden.Settings.Manage');
        $Sender->SetData('Title', T('Applicant Accepter Settings'));
        $Sender->AddSideMenu('dashboard/settings/plugins');
        $Conf = new ConfigurationModule($Sender);
        $Conf->Initialize(array(
            'Plugins.ApplicantAccepter.ApplicantRoleID',
            'Plugins.ApplicantAccepter.ShowDiscoveryText'
        ));
        $Conf->RenderAll();
    } 
    
    // store the current user to accepted applicants
    public function userController_afterApproveUser_handler ($Sender) {
        $InsertUserID = Gdn::Session()->UserID;
        Gdn::UserModel()->SetField($Sender->EventArguments['UserID'], 'ApplicantAccepter', $InsertUserID);
    }
    
    // if accepting is done by changing role
    public function userModel_beforeSave_handler ($Sender) {
        $InsertUserID = Gdn::Session()->UserID;
        $User = $Sender->EventArguments['LoadedUser'];
        $UserID = $User->UserID;

        // we are not interested in users changing their own profile
        if ($InsertUserID == $UserID) {
            return;
        }

        // if roles are not changed, we have nothing to do
        $FormPostValues = $Sender->EventArguments['FormPostValues'];
        if (!isset($FormPostValues['RoleID'])) {
            return;
        }
        
        // if user is no Applicant we have nothing to do
        $ApplicantRoleID = C('Plugins.ApplicantAccepter.ApplicantRoleID', 4);
        $UserRoles = ConsolidateArrayValuesByKey(Gdn::UserModel()->GetRoles($UserID), 'RoleID');
        if (!in_array($ApplicantRoleID, $UserRoles)) {
            return;
        }

        // is Applicant role still in the roles to be saved? Also not interesting
        if (in_array($ApplicantRoleID, $FormPostValues['RoleID'])) {
            return;
        }
        
        // now that we've excluded everything else we have to keep the inserting user
        $this->_InsertUserID = $InsertUserID;
    }

    // add Applicant accepter info if it has been set before
    public function userModel_afterSave_handler ($Sender) {
        if ($this->_InsertUserID > 0) {
            Gdn::UserModel()->SetField($Sender->EventArguments['UserID'], 'ApplicantAccepter', $this->_InsertUserID);
        }
    }
    
    // add Applicant accepter to user list
    public function userController_userCell_handler ($Sender) {
        if (!isset($Sender->EventArguments['User'])) {
            // print out header
            echo '<th>'.T('Applicant Accepter').'</th>';
            if (C('Plugins.ApplicantAccepter.ShowDiscoveryText', true) == true) {
                echo '<th>'.T('Reason for Joining').'</th>';
            }
        } else {
            // get the info of the applicant approver
            $ApplicantAccepterID = $Sender->EventArguments['User']->ApplicantAccepter;
            $ApplicantAccepterName = '';
            if ($ApplicantAccepterID > 0) {
                $ApplicantAccepterName = Gdn::UserModel()->GetID($ApplicantAccepterID)->Name;
            }
            echo '<td>'.$ApplicantAccepterName.'</td>';
            // show reason for joining if it 
            if (C('Plugins.ApplicantAccepter.ShowDiscoveryText', true) == true) {
                echo '<td>'.$Sender->EventArguments['User']->DiscoveryText.'</td>';
            }
        }
    }
    
    // add possibility to style table
    public function base_render_before ($Sender) {
        if ($Sender->MasterView == 'admin') {
            $Sender->AddCssFile('applicantaccepter.css', 'plugins/ApplicantAccepter');
        }
    }    
}
