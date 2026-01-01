<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Models\DomainProvisioningJob;
use App\Models\SystemKV;
use App\Support\Env;
use App\Services\PleskClient;

$auto = Env::bool('PLESK_AUTO_PROVISION', true);
if (!$auto) {
    echo "PLESK_AUTO_PROVISION disabled\n";
    exit(0);
}

$site = (string)(Env::get('PLESK_SITE_NAME', '') ?? '');
if ($site === '') {
    echo "Missing PLESK_SITE_NAME\n";
    exit(1);
}

$jobs = DomainProvisioningJob::listPending(25);
if (!$jobs) {
    echo "No pending jobs\n";
    try {
        SystemKV::set('plesk_provision_last_run', 'no_pending');
    } catch (Throwable $e) {
    }
    exit(0);
}

try {
    SystemKV::set('plesk_provision_last_run', 'started');
} catch (Throwable $e) {
}

foreach ($jobs as $job) {
    $id = (int)$job['id'];
    $domain = (string)$job['domain'];
    DomainProvisioningJob::incrementAttempts($id);

    $res = PleskClient::createDomainAlias($site, $domain);
    if ($res['ok']) {
        DomainProvisioningJob::markSuccess($id);
        echo "OK alias created: {$domain}\n";
        continue;
    }
    $err = ($res['error'] ?? 'unknown') . ' (http ' . (string)$res['status'] . ')';
    DomainProvisioningJob::markFailed($id, $err);
    echo "FAIL {$domain}: {$err}\n";
}

try {
    SystemKV::set('plesk_provision_last_run', 'finished');
} catch (Throwable $e) {
}
