<?php
declare(strict_types=1);

namespace App;

final class QuestionCatalog
{
    public static function workloads(): array
    {
        return [
            'exchange' => 'Exchange / Microsoft 365',
            'identity' => 'Active Directory / Entra ID',
            'sharepoint' => 'SharePoint / OneDrive',
            'teams' => 'Microsoft Teams',
            'intune' => 'Intune / Device Management',
            'azure' => 'Azure / AVD',
        ];
    }

    public static function questions(array $workloads): array
    {
        $common = [
            ['id' => 'common.business_goal', 'label' => 'What business outcome is driving the migration?', 'type' => 'textarea'],
            ['id' => 'common.target_date', 'label' => 'Is there a required completion date or business deadline?', 'type' => 'text'],
            ['id' => 'common.compliance', 'label' => 'Which security or compliance requirements apply?', 'type' => 'text'],
            ['id' => 'common.locations', 'label' => 'How many users and physical locations are in scope?', 'type' => 'text'],
            ['id' => 'common.change_window', 'label' => 'What change windows and outage restrictions apply?', 'type' => 'textarea'],
        ];

        $byWorkload = [
            'exchange' => [
                ['id' => 'exchange.source', 'label' => 'Where are mailboxes currently hosted?', 'type' => 'select', 'options' => ['Exchange on-premises', 'Exchange Online', 'Hybrid Exchange', 'Other']],
                ['id' => 'exchange.version', 'label' => 'What Exchange version and cumulative update are installed?', 'type' => 'text'],
                ['id' => 'exchange.mailboxes', 'label' => 'How many user, shared, room, and equipment mailboxes are in scope?', 'type' => 'textarea'],
                ['id' => 'exchange.data', 'label' => 'What is the total mailbox data volume and largest mailbox?', 'type' => 'text'],
                ['id' => 'exchange.features', 'label' => 'Are public folders, archives, journaling, SMTP relay, or third-party gateways used?', 'type' => 'textarea'],
            ],
            'identity' => [
                ['id' => 'identity.forests', 'label' => 'How many AD forests, domains, and domain controllers exist?', 'type' => 'text'],
                ['id' => 'identity.sync', 'label' => 'Is Entra Connect Sync or Cloud Sync currently deployed?', 'type' => 'text'],
                ['id' => 'identity.objects', 'label' => 'How many users, groups, service accounts, and devices are in scope?', 'type' => 'textarea'],
                ['id' => 'identity.auth', 'label' => 'Describe MFA, Conditional Access, federation, and passwordless requirements.', 'type' => 'textarea'],
            ],
            'sharepoint' => [
                ['id' => 'sharepoint.source', 'label' => 'What are the source platforms and versions?', 'type' => 'text'],
                ['id' => 'sharepoint.volume', 'label' => 'How many sites and OneDrive accounts, and how much total data?', 'type' => 'textarea'],
                ['id' => 'sharepoint.custom', 'label' => 'Are custom solutions, workflows, forms, or unsupported file types present?', 'type' => 'textarea'],
            ],
            'teams' => [
                ['id' => 'teams.counts', 'label' => 'How many teams, channels, chats, and meetings are in scope?', 'type' => 'textarea'],
                ['id' => 'teams.voice', 'label' => 'Is Teams Phone, Direct Routing, Operator Connect, or calling plan migration required?', 'type' => 'textarea'],
                ['id' => 'teams.apps', 'label' => 'Which Teams applications, bots, connectors, and guest users must be addressed?', 'type' => 'textarea'],
            ],
            'intune' => [
                ['id' => 'intune.devices', 'label' => 'How many Windows, macOS, iOS, and Android devices are in scope?', 'type' => 'textarea'],
                ['id' => 'intune.join', 'label' => 'What are the current and desired device join states?', 'type' => 'text'],
                ['id' => 'intune.controls', 'label' => 'Which policies, applications, certificates, VPNs, and compliance controls must migrate?', 'type' => 'textarea'],
            ],
            'azure' => [
                ['id' => 'azure.resources', 'label' => 'Which subscriptions, workloads, resource types, and regions are in scope?', 'type' => 'textarea'],
                ['id' => 'azure.network', 'label' => 'Describe connectivity, firewalls, DNS, VPN/ExpressRoute, and IP dependencies.', 'type' => 'textarea'],
                ['id' => 'azure.avd', 'label' => 'Describe AVD host pools, images, profiles, applications, and user concurrency.', 'type' => 'textarea'],
            ],
        ];

        foreach ($workloads as $workload) {
            $common = array_merge($common, $byWorkload[$workload] ?? []);
        }
        return $common;
    }
}
