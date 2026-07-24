<?php

$root = dirname(__DIR__);
$sslDir = $root.DIRECTORY_SEPARATOR.'server'.DIRECTORY_SEPARATOR.'ssl';
if (! is_dir($sslDir)) {
    mkdir($sslDir, 0775, true);
}

$configPath = $sslDir.DIRECTORY_SEPARATOR.'openssl-local.cnf';
$certPath = $sslDir.DIRECTORY_SEPARATOR.'hcis-local.crt';
$keyPath = $sslDir.DIRECTORY_SEPARATOR.'hcis-local.key';

$config = <<<CONF
[ req ]
distinguished_name = dn
prompt = no
req_extensions = v3_req

[ dn ]
CN = hcis.ensys.id

[ v3_req ]
basicConstraints = critical, CA:FALSE
keyUsage = critical, digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names

[ alt_names ]
DNS.1 = hcis.ensys.id
DNS.2 = localhost
IP.1 = 127.0.0.1
CONF;

file_put_contents($configPath, $config);

$privateKey = openssl_pkey_new([
    'config' => $configPath,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 2048,
]);

if (! $privateKey) {
    throw new RuntimeException('Cannot create private key: '.openssl_error_string());
}

$csr = openssl_csr_new(
    ['commonName' => 'hcis.ensys.id'],
    $privateKey,
    [
        'config' => $configPath,
        'digest_alg' => 'sha256',
        'req_extensions' => 'v3_req',
    ],
);

if (! $csr) {
    throw new RuntimeException('Cannot create CSR: '.openssl_error_string());
}

$cert = openssl_csr_sign(
    $csr,
    null,
    $privateKey,
    1825,
    [
        'config' => $configPath,
        'digest_alg' => 'sha256',
        'x509_extensions' => 'v3_req',
    ],
);

if (! $cert) {
    throw new RuntimeException('Cannot sign certificate: '.openssl_error_string());
}

openssl_x509_export($cert, $certOut);
openssl_pkey_export($privateKey, $keyOut, null, ['config' => $configPath]);

file_put_contents($certPath, $certOut);
file_put_contents($keyPath, $keyOut);

echo $certPath.PHP_EOL;
