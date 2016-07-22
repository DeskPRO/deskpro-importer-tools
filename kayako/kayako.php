<?php

########################################################################################################################
# CONFIG
########################################################################################################################

$CONFIG = [];
$CONFIG['dbinfo'] = [
    'host'     => 'localhost',
    'port'     => '3306',
    'user'     => 'root',
    'password' => '',
    'dbname'   => 'kayako',
    'driver'   => 'pdo_mysql',
];

########################################################################################################################
# Do not edit below this line
########################################################################################################################

/** @var \Application\ImportBundle\ScriptHelper\OutputHelper $output */
/** @var \Application\ImportBundle\ScriptHelper\WriteHelper $writer */
/** @var \Application\ImportBundle\ScriptHelper\DbHelper $db */

$db->setCredentials($CONFIG['dbinfo']);


$output->startSection('Organizations');
$pager = $db->getPager('select * from swuserorganizations');
while ($data = $pager->next()) {
    foreach ($data as $n) {
        $organization = [
            'name' => $n['organizationname'],
        ];

        // set organization contact data
        // website
        if ($n['website']) {
            if ($writer->getFormattedUrl($n['website'])) {
                $output->debug('Organization has valid website url, adding to its contact data');
                $organization['contact_data']['website'][] = [
                    'url' => $writer->getFormattedUrl($n['website']),
                ];
            } else {
                $output->warning("Organization has invalid website url `{$n['website']}`, skipping");
            }
        }

        // phone numbers
        if ($n['phone']) {
            if ($writer->getFormattedNumber($n['phone'])) {
                $output->debug('Organization has valid phone number, adding to its contact data');
                $organization['contact_data']['phone'][] = [
                    'number' => $writer->getFormattedNumber($n['phone']),
                    'type'   => 'phone',
                ];
            } else {
                $output->warning("Organization has invalid phone number `{$n['phone']}`, skipping");
            }
        }

        if ($n['fax']) {
            if ($writer->getFormattedNumber($n['fax'])) {
                $output->debug('Organization has valid fax, adding, adding to its contact data');
                $organization['contact_data']['phone'][] = [
                    'number' => $writer->getFormattedNumber($n['fax']),
                    'type'   => 'fax',
                ];
            } else {
                $output->warning("Organization has invalid fax number `{$n['phone']}`, skipping");
            }
        }

        // address
        if ($n['address']) {
            $organization['contact_data']['address'][] = [
                'address' => $n['address'],
                'city'    => $n['city'],
                'zip'     => $n['postalcode'],
                'state'   => $n['state'],
                'country' => $n['country'],
            ];
        }

        $writer->writeOrganization($n['userorganizationid'], $organization);
    }
}

$output->startSection('People');
$pager = $db->getPager('select * from swstaff');
while ($data = $pager->next()) {
    foreach ($data as $n) {
        $writer->writePerson('agent_'.$n['staffid'], [
            'name'        => $n['fullname'],
            'emails'      => [$n['email']],
            'is_disabled' => !$n['isenabled'],
            'is_agent'    => true,
        ]);
    }
}

$pager = $db->getPager('select * from swusers');
while ($data = $pager->next()) {
    foreach ($data as $n) {
        $person = [
            'name'         => $n['fullname'],
            'emails'       => ['imported.user.' . $n['userid'] . '@example.com'],
            'is_disabled'  => !$n['isenabled'],
            'organization' => $n['userorganizationid'],
        ];

        if ($person['organization']) {
            $person['organization_position'] = $n['userdesignation'];
        }

        if ($n['phone']) {
            if ($writer->getFormattedNumber($n['phone'])) {
                $output->debug('User has valid phone number, adding to its contact data');
                $person['contact_data']['phone'][] = [
                    'number' => $writer->getFormattedNumber($n['phone']),
                    'type'   => 'phone',
                ];
            } else {
                $output->warning("User has invalid phone number `{$n['phone']}`, skipping");
            }
        }

        $writer->writePerson('user_'.$n['userid'], $person);
    }
}

$output->startSection('Tickets');
$pager = $db->getPager('select * from swtickets');

$statusMapping = [
    'Open'        => 'awaiting_agent',
    'In Progress' => 'awaiting_agent',
    'Closed'      => 'resolved',
];

while ($data = $pager->next()) {
    foreach ($data as $n) {
        $ticket = [
            'subject'    => $n['subject'],
            'person'     => $n['userid'] ? 'user_'.$n['userid'] : null,
            'agent'      => $n['staffid'] ? 'agent_'.$n['staffid'] : null,
            'department' => $n['departmenttitle'],
            'status'     => $statusMapping[$n['ticketstatustitle']],
        ];

        $messagePager = $db->getPager('select * from swticketposts WHERE ticketid = :ticket_id', [
            'ticket_id' => $n['ticketid'],
        ]);

        while ($messageData = $messagePager->next()) {
            foreach ($messageData as $m) {
                $ticket['messages'][] = [
                    'oid'     => $m['ticketpostid'],
                    'person'  => $m['userid'],
                    'message' => $m['contents'],
                ];
            }
        }

        $writer->writeTicket($n['ticketid'], $ticket);
    }
}