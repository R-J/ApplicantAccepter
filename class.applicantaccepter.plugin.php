<?php

$PluginInfo['ApplicantAccepter'] = array(
    'Name' => 'Applicant Accepter',
    'Description' => 'Adds information to user table who accepted an applicant.',
    'Version' => '0.2',
    'RequiredApplications' => array('Vanilla' => '2.2'),
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
        touchConfig('Plugins.ApplicantAccepter.ApplicantRoleID', 4);
        touchConfig('Plugins.ApplicantAccepter.ShowDiscoveryText', true);

        // change user table
        $this->structure();
    }

    // called by setup, adds column to user table
    public function structure() {
        Gdn::database()->structure()
            ->table('User')
            ->column('ApplicantAccepter', 'int(11)', 0)
            ->set();
    }
    
    // very basic settings screen
    public function settingsController_applicantAccepter_create($sender) {
        $sender->permission('Garden.Settings.Manage');
        $sender->setData('Title', t('Applicant Accepter Settings'));
        $sender->addSideMenu('dashboard/settings/plugins');
        $configurationModule = new configurationModule($sender);
        $configurationModule->initialize(array(
            'Plugins.ApplicantAccepter.ApplicantRoleID',
            'Plugins.ApplicantAccepter.ShowDiscoveryText'
        ));
        $configurationModule->renderAll();
    }

    // store the current user to accepted applicants
    public function userController_afterApproveUser_handler($sender, $args) {
        Gdn::userModel()->setField(
            $args['UserID'],
            'ApplicantAccepter',
            Gdn::session()->UserID
        );
    }

    // if accepting is done by changing role
    public function userModel_beforeSave_handler($sender, $args) {
        $insertUserID = Gdn::session()->UserID;
        $user = $args['LoadedUser'];
        $userID = $user->UserID;

        // we are not interested in users changing their own profile
        if ($insertUserID == $userID) {
            return;
        }

        // if roles are not changed, we have nothing to do
        $formPostValues = $args['FormPostValues'];
        if (!isset($formPostValues['RoleID'])) {
            return;
        }

        // if user is no Applicant we have nothing to do
        $applicantRoleID = c('Plugins.ApplicantAccepter.ApplicantRoleID', 4);
        $userRoles = array_column(Gdn::userModel()->getRoles($UserID), 'RoleID');
        if (!in_array($applicantRoleID, $userRoles)) {
            return;
        }

        // is Applicant role still in the roles to be saved? Also not interesting
        if (in_array($applicantRoleID, $formPostValues['RoleID'])) {
            return;
        }

        // now that we've excluded everything else we have to keep the inserting user
        $this->_InsertUserID = $insertUserID;
    }

    // add Applicant accepter info if it has been set before
    public function userModel_afterSave_handler($sender, $args) {
        if ($this->_InsertUserID > 0) {
            Gdn::UserModel()->setField(
                $args['UserID'],
                'ApplicantAccepter',
                $this->_InsertUserID
            );
        }
    }

    // add Applicant accepter to user list
    public function userController_userCell_handler($sender, $args) {
        if (!isset($args['User'])) {
            // print out header
            echo '<th>'.t('Applicant Accepter').'</th>';
            if (c('Plugins.ApplicantAccepter.ShowDiscoveryText', true) == true) {
                echo '<th>'.t('Reason for Joining').'</th>';
            }
        } else {
            // get the info of the applicant approver
            $applicantAccepterID = $args['User']->ApplicantAccepter;
            $applicantAccepterName = '';
            if ($applicantAccepterID > 0) {
                $applicantAccepterName = Gdn::UserModel()->getID($applicantAccepterID)->Name;
            }
            echo '<td>'.$applicantAccepterName.'</td>';
            // show reason for joining if it
            if (c('Plugins.ApplicantAccepter.ShowDiscoveryText', true) == true) {
                echo '<td>'.$args['User']->DiscoveryText.'</td>';
            }
        }
    }

    // add possibility to style table
    public function base_render_before($sender) {
        if ($sender->MasterView == 'admin') {
            $sender->addCssFile(
                'applicantaccepter.css',
                'plugins/ApplicantAccepter'
            );
        }
    }
}
