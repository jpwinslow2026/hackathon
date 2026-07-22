<?php
declare(strict_types=1);

namespace App;

final class QuestionCatalog
{
    public static function workloads(): array
    {
        return [
            'email' => 'Email',
            'teams' => 'Teams',
            'sharepoint' => 'SharePoint',
            'onedrive' => 'OneDrive',
            'intune' => 'Intune',
        ];
    }

    public static function questions(array $workloads = []): array
    {
        return [
            ['section' => 'Current email environment', 'id' => 'email.current_hosting', 'label' => 'Where is email currently hosted?', 'type' => 'select', 'options' => ['On-premises Exchange', 'Microsoft 365 / Exchange Online', 'Another hosted email provider', 'Hybrid Exchange', 'Other']],
            ['section' => 'Current email environment', 'id' => 'email.platform_details', 'label' => 'What email platform, version, provider, and domain names are currently in use?', 'type' => 'textarea'],
            ['section' => 'Current email environment', 'id' => 'email.user_count', 'label' => 'How many user mailboxes are in scope?', 'type' => 'number'],
            ['section' => 'Current email environment', 'id' => 'email.shared_count', 'label' => 'How many shared, room, and equipment mailboxes are in scope?', 'type' => 'text'],
            ['section' => 'Current email environment', 'id' => 'email.service_accounts', 'label' => 'How many service accounts exist, and what are they used for?', 'type' => 'textarea'],
            ['section' => 'Current email environment', 'id' => 'email.data_volume', 'label' => 'What is the total mailbox data volume, average mailbox size, and largest mailbox?', 'type' => 'textarea'],
            ['section' => 'Current email environment', 'id' => 'email.archives', 'label' => 'Are mailbox archives being migrated? Describe native archives, PST files, journaling, or any third-party archive solution.', 'type' => 'textarea'],
            ['section' => 'Current email environment', 'id' => 'email.routing', 'label' => 'How is inbound and outbound mail routed today?', 'type' => 'textarea'],
            ['section' => 'Current email environment', 'id' => 'email.filtering', 'label' => 'Is a third-party mail filtering or security service in use?', 'type' => 'text'],
            ['section' => 'Current email environment', 'id' => 'email.smtp', 'label' => 'Which devices or applications send email, such as scanners, copiers, monitoring systems, or line-of-business applications?', 'type' => 'textarea'],
            ['section' => 'Current email environment', 'id' => 'email.special_features', 'label' => 'Are public folders, distribution groups, transport rules, delegated mailboxes, retention policies, or compliance searches in use?', 'type' => 'textarea'],

            ['section' => 'Microsoft 365 target environment', 'id' => 'm365.tenant_exists', 'label' => 'Does the customer already have a Microsoft 365 tenant?', 'type' => 'select', 'options' => ['Yes', 'No', 'Unknown']],
            ['section' => 'Microsoft 365 target environment', 'id' => 'm365.tenant_type', 'label' => 'Which Microsoft 365 tenant security environment is required?', 'type' => 'select', 'options' => ['Commercial', 'GCC', 'GCC High', 'Not yet determined']],
            ['section' => 'Microsoft 365 target environment', 'id' => 'm365.workloads', 'label' => 'Which workloads are included in the migration?', 'type' => 'multiselect', 'options' => ['Email', 'Teams', 'SharePoint', 'OneDrive', 'Intune']],
            ['section' => 'Microsoft 365 target environment', 'id' => 'm365.licensing', 'label' => 'What Microsoft 365 licensing is owned or expected, and are there security, voice, or compliance add-ons?', 'type' => 'textarea'],
            ['section' => 'Microsoft 365 target environment', 'id' => 'm365.domains', 'label' => 'Which domains will be verified and used in Microsoft 365?', 'type' => 'textarea'],
            ['section' => 'Microsoft 365 target environment', 'id' => 'm365.identity', 'label' => 'Will identities be cloud-only, synchronized from Active Directory, or federated?', 'type' => 'select', 'options' => ['Cloud-only', 'Entra Connect Sync', 'Entra Cloud Sync', 'Federated', 'Not yet determined']],
            ['section' => 'Microsoft 365 target environment', 'id' => 'm365.security', 'label' => 'Describe MFA, Conditional Access, retention, DLP, eDiscovery, encryption, and other security or compliance requirements.', 'type' => 'textarea'],
            ['section' => 'Microsoft 365 target environment', 'id' => 'm365.sso', 'label' => 'Are there SSO integrations with the existing environment or applications that must be migrated or recreated?', 'type' => 'textarea'],

            ['section' => 'Additional workloads', 'id' => 'teams.requirements', 'label' => 'For Teams, what teams, channels, chat history, meetings, guest access, apps, and voice services must be migrated?', 'type' => 'textarea'],
            ['section' => 'Additional workloads', 'id' => 'sharepoint.requirements', 'label' => 'For SharePoint, how many sites and how much data must move? Include workflows, forms, customizations, and permissions.', 'type' => 'textarea'],
            ['section' => 'Additional workloads', 'id' => 'onedrive.requirements', 'label' => 'For OneDrive, how many users and how much data must move? Note sharing links, known-folder move, and ownership concerns.', 'type' => 'textarea'],
            ['section' => 'Additional workloads', 'id' => 'intune.requirements', 'label' => 'For Intune, how many devices by platform are in scope, and which applications, policies, certificates, and enrollment methods are required?', 'type' => 'textarea'],

            ['section' => 'Project delivery', 'id' => 'project.business_goal', 'label' => 'What business outcomes should the migration achieve?', 'type' => 'textarea'],
            ['section' => 'Project delivery', 'id' => 'project.timeline', 'label' => 'What are the desired timeline, hard deadlines, blackout dates, and business drivers?', 'type' => 'textarea'],
            ['section' => 'Project delivery', 'id' => 'project.locations', 'label' => 'How many locations, business units, and user populations are affected?', 'type' => 'textarea'],
            ['section' => 'Project delivery', 'id' => 'project.change_window', 'label' => 'What change windows, outage restrictions, pilot groups, and communication requirements apply?', 'type' => 'textarea'],
            ['section' => 'Project delivery', 'id' => 'project.responsibilities', 'label' => 'Who will provide DNS changes, licensing, application remediation, user communications, testing, and executive approvals?', 'type' => 'textarea'],
            ['section' => 'Project delivery', 'id' => 'project.other', 'label' => 'What other requirements, constraints, risks, or assumptions should be captured?', 'type' => 'textarea'],
        ];
    }
}
