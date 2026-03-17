<?php

namespace Modules\Platform\Services;

use Modules\Platform\Models\Tld;

class WhoisService
{
    public function checkPattern(string $pattern, $output): bool
    {
        return (bool) preg_match('/'.$pattern.'/i', (string) $output);
    }

    public function whoisServer($server, string $domain): string|false
    {
        $timeout = 10;
        $port = 43;

        $openConnection = @fsockopen($server, $port, $errno, $errstr, $timeout);

        if (! $openConnection) {
            return false;
        }

        fwrite($openConnection, $domain."\r\n");
        $out = '';
        while (! feof($openConnection)) {
            $out .= fgets($openConnection);
        }

        fclose($openConnection);

        return $out;
    }

    public function getWhoisData(string $url, $domainModel): array
    {
        $response = ['success' => false];
        if ($url && $domainModel::isValidUrl($url)) {
            $url = $domainModel::getDomain($url);
            $tld = $domainModel::getTld($url);
            $tld = Tld::query()->where('tld', '=', $tld)->first();

            if ($tld) {
                $data = $this->whoisServer($tld->whois_server, $url);

                if (! $this->checkPattern($tld->pattern, $data)) {
                    $array = explode("\r\n", $data);
                    $array1 = [];
                    $nameServerArray = [];
                    $prefix = ': ';
                    $i = 0;
                    $j = 0;

                    foreach ($array as $row) {
                        if (
                            str_contains($row, 'Updated Date: ') ||
                            str_contains($row, 'Creation Date: ') ||
                            str_contains($row, 'Registry Expiry Date: ') ||
                            str_contains($row, 'Registrar: ')
                        ) {
                            $array1[$i++] = $row;
                        }

                        if (str_contains($row, 'Name Server: ')) {
                            $nameServerArray[$j++] = $row;
                        }
                    }

                    foreach ($array1 as $row) {
                        if (str_contains($row, 'Updated Date: ')) {
                            $string = 'update';
                        } elseif (str_contains($row, 'Creation Date: ')) {
                            $string = 'created';
                        } elseif (str_contains($row, 'Registry Expiry Date: ')) {
                            $string = 'expiry';
                        } elseif (str_contains($row, 'Registrar: ')) {
                            $string = 'registrar';
                        } else {
                            continue;
                        }

                        $index = strpos($row, $prefix) + strlen($prefix);
                        $new_value = substr($row, $index);

                        if ($string === 'update') {
                            $response['updated_on'] = substr($new_value, 0, 10);
                        } elseif ($string === 'created') {
                            $response['registered_on'] = substr($new_value, 0, 10);
                        } elseif ($string === 'expiry') {
                            $response['expires_on'] = substr($new_value, 0, 10);
                        } elseif ($string === 'registrar') {
                            $response['domain_registrar'] = $new_value;
                        }
                    }

                    foreach ($nameServerArray as $k => $row) {
                        $index = strpos($row, $prefix) + strlen($prefix);
                        $nameServerArray[$k] = substr($row, $index);
                    }

                    if ($nameServerArray !== []) {
                        $response['name_server_1'] = $nameServerArray[0] ?? '';
                        $response['name_server_2'] = $nameServerArray[1] ?? '';
                        $response['name_server_3'] = $nameServerArray[2] ?? '';
                        $response['name_server_4'] = $nameServerArray[3] ?? '';
                        $response['success'] = true;
                    } else {
                        $response['parseFailed'] = $data;
                    }
                } else {
                    $response['domain_not_registered'] = 1;
                }
            } else {
                $response['extNotValid'] = 1;
            }
        }

        return $response;
    }
}
