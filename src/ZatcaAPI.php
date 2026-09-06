<?php

namespace Saleh7\Zatca;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Saleh7\Zatca\Api\ComplianceCertificateResult;
use Saleh7\Zatca\Api\ProductionCertificateResult;
use Saleh7\Zatca\Exceptions\ZatcaApiException;
use Saleh7\Zatca\Exceptions\ZatcaStorageException;

/**
 * ZATCA E-Invoicing API Client for compliance and reporting operations.
 */
class ZatcaAPI
{
    private const ENVIRONMENTS = [
        'sandbox'    => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/',
        'simulation' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/',
        'production' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core/',
    ];


    private const API_VERSION = 'V2';
    private const SUCCESS_STATUS_CODES = [200, 202];

    private ClientInterface $httpClient;
    private bool $allowWarnings = false;
    private string $environment;

    /**
     * @param string $environment API environment (simulation|simulation|production)
     * @param ClientInterface|null $client Optional HTTP client to enable dependency injection.
     * @throws InvalidArgumentException For invalid environment.
     */
    public function __construct(string $environment = 'production', ?ClientInterface $client = null)
    {
        if (!isset(self::ENVIRONMENTS[$environment])) {
            $validEnvs = implode(', ', array_keys(self::ENVIRONMENTS));
            throw new InvalidArgumentException("Invalid environment. Valid options: $validEnvs");
        }
        $this->environment = $environment;
        $this->httpClient  = $client ?? new Client([
            'base_uri' => $this->getBaseUri(),
            'timeout'  => 30,
            'verify'   => true,
        ]);
    }

    /**
     * Returns the base URI for the current environment.
     *
     * @return string
     */
    public function getBaseUri(): string
    {
        return self::ENVIRONMENTS[$this->environment];
    }

    /**
     * Load CSR file content.
     *
     * @param string $path File path of the CSR.
     * @return string CSR content.
     * @throws \Exception If file is not found or unreadable.
     */
    public function loadCSRFromFile(string $path): string
    {
        if (!file_exists($path)) {
            throw new \Exception("File not found: {$path}");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \Exception("Could not read file: {$path}");
        }
        return $content;
    }

    /**
     * Enable/disable acceptance of warning responses.
     */
    public function setWarningHandling(bool $allow): void
    {
        $this->allowWarnings = $allow;
    }

    public function requestComplianceCertificate(string $csr, string $otp)
    {
        try {
            $response = $this->sendRequest(
                'POST',
                'compliance',
                [
                    'OTP' => $otp,
                    'Accept-Version' => 'V2',
                    'User-Agent'    => 'PostmanRuntime/7.43.2',
                    'Accept-Encoding' => 'gzip, deflate, br'
                ],
                ['csr' => "$csr"]
            );

            return [
                new ComplianceCertificateResult(
                    $this->formatCertificate($response['binarySecurityToken'] ?? ''),
                    $response['secret'] ?? '',
                    $response['requestID'] ?? '',
                ),
                $response['binarySecurityToken'] ?? ''
            ];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = $e->getResponse()->getBody()->getContents();
            throw new \Exception("ZATCA API Client error: $responseBody", 0, $e);
        }
    }

    /**
     * Validate invoice compliance with ZATCA regulations.
     *
     * @param string $certificate The certificate for authentication.
     * @param string $secret      API secret.
     * @param string $signedInvoice Signed invoice content.
     * @param string $invoiceHash Invoice hash.
     * @param string $uuid      Unique invoice identifier.
     * @return array API response data.
     * @throws ZatcaApiException For API communication errors.
     */
    public function validateInvoiceCompliance(
        string $certificate,
        string $secret,
        string $signedInvoice,
        string $invoiceHash,
        string $uuid
    ) {
        try {
            return $this->sendRequest(
                'POST',
                'compliance/invoices',
                ['Accept-Language' => 'en', 'Content-Type' => 'application/json'],
                [
                    'invoiceHash' => $invoiceHash,
                    'uuid'        => $uuid,
                    'invoice'     => base64_encode($signedInvoice),
                ],
                $this->createAuthHeaders($certificate, $secret)
            );
        } catch (ZatcaApiException $e) {
            // Log::channel("zatca_logs")->error("validateInvoiceCompliance validateInvoiceCompliance ZatcaAPI" . $e->getMessage() . $e->getLine() . $e->getFile());
            return [
                "status" => 400,
                "message" => $e->getContext() ?? [],
            ];
        }
    }

    /**
     * Request production certificate using compliance credentials.
     *
     * @param string $certificate         The certificate for authentication.
     * @param string $secret              API secret.
     * @param string $complianceRequestId Compliance request ID.
     * @return ProductionCertificateResult
     * @throws ZatcaApiException For API communication errors.
     */
    public function requestProductionCertificate(
        string $certificate,
        string $secret,
        string $complianceRequestId
    ): ProductionCertificateResult {
        $response = $this->sendRequest(
            'POST',
            'production/csids',
            ['Content-Type' => 'application/json'],
            ['compliance_request_id' => $complianceRequestId],
            $this->createAuthHeaders($certificate, $secret)
        );

        return new ProductionCertificateResult(
            $this->formatCertificate($response['binarySecurityToken'] ?? ''),
            $response['secret'] ?? '',
            $response['requestID'] ?? ''
        );
    }

    /**
     * Submit invoice for clearance reporting.
     *
     * @param string $certificate  The certificate for authentication.
     * @param string $secret       API secret.
     * @param string $signedInvoice Signed invoice content.
     * @param string $invoiceHash  Invoice hash.
     * @param string $egsUuid      Unique invoice identifier.
     * @return array API response data.
     * @throws ZatcaApiException For API communication errors.
     */
    public function submitClearanceInvoice(
        string $certificate,
        string $secret,
        string $signedInvoice,
        string $invoiceHash,
        string $egsUuid
    ): array {
        return $this->sendRequest(
            'POST',
            'invoices/clearance/single',
            ['Clearance-Status' => '1', 'Accept-Language' => 'en'],
            [
                'invoiceHash' => $invoiceHash,
                'uuid'        => $egsUuid,
                'invoice'     => base64_encode($signedInvoice),
            ],
            $this->createAuthHeaders($certificate, $secret)
        );
    }

    /**
     * Generate authentication headers for secured endpoints.
     *
     * @param string $certificate
     * @param string $secret
     * @return array
     */
    public function createAuthHeaders(string $certificate, string $secret): array
    {
        $cleanCert   = trim($certificate);
        $credentials = base64_encode($cleanCert . ':' . $secret);
        return ['Authorization' => 'Basic ' . $credentials];
    }

public function sendRequest(
    string $method,
    string $endpoint,
    array $headers = [],
    array $payload = [],
    array $authHeaders = []
) {
    try {
        // دمج الهيدرز
        $mergedHeaders = array_merge(
            [
                'Accept-Version' => self::API_VERSION,
                'Accept'         => 'application/json',
            ],
            $headers,
            $authHeaders
        );

        // تجهيز الخيارات
        $options = [
            'headers' => $mergedHeaders,
            'json'    => $payload,// 
        ];

        // بناء الرابط النهائي
        $url = $this->getBaseUri() . $endpoint;

        // إرسال الطلب
        $response = $this->httpClient->request($method, $url, $options);
        $statusCode = $response->getStatusCode();

        // التحقق من نجاح الرد
        if (!$this->isSuccessfulResponse($statusCode)) {
            $responseBody = $response->getBody()->getContents();
            Log::channel('zatca_logs')->error("❌ [ZATCA] Non-success response {$statusCode}: {$responseBody}");
            throw new ZatcaApiException('Invalid response', ['response' => $responseBody]);
        }

        // إرجاع الرد بعد التحليل
        return $this->parseResponse($response);

    } catch (\GuzzleHttp\Exception\RequestException $e) {
        // التقاط الرد حتى بعد استهلاك stream
        $responseBody = 'NO RESPONSE BODY';
        if ($e->hasResponse()) {
            try {
                $responseBody = (string) $e->getResponse()->getBody();
            } catch (\Throwable $t) {
                $responseBody = 'FAILED TO READ BODY: ' . $t->getMessage();
            }
        }

        // Log::channel('zatca_logs')->error("❌ [ZATCA] RequestException: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        // Log::channel('zatca_logs')->error("🧾 [ZATCA] Response body: {$responseBody}");
        // Log::channel('zatca_logs')->error("🧭 Stack trace:\n" . $e->getTraceAsString());

        // 👇 نضيف البودي داخل الإكسبشن علشان نقدر نوصله لاحقًا
        $e->zatcaBody = $responseBody;

        throw $e;

    } catch (\Throwable $e) {
        Log::channel('zatca_logs')->error("💥 [ZATCA] sendRequest fatal: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        Log::channel('zatca_logs')->error("🧭 Stack trace:\n" . $e->getTraceAsString());
        throw $e;
    }
}



    /**
     * Validate HTTP status code against success criteria.
     */
    private function isSuccessfulResponse(int $statusCode): bool
    {
        return in_array($statusCode, self::SUCCESS_STATUS_CODES, true) &&
            ($this->allowWarnings || $statusCode === 200 || $statusCode === 202);
    }

    /**
     * Parse API response.
     *
     * @param ResponseInterface $response
     * @return array
     * @throws ZatcaApiException If response JSON is invalid.
     */
    private function parseResponse(ResponseInterface $response): array
    {
        $content = $response->getBody()->getContents();
        
        $data    = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ZatcaApiException('Failed to parse API response: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Format certificate string with PEM boundaries.
     *
     * @param string $base64Certificate
     * @return string
     */
    private function formatCertificate(string $base64Certificate): string
    {
        $decoded = base64_decode($base64Certificate);
        return $decoded;
    }

    public function saveToJson($data, string $filePath): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \Exception("Failed to encode data to JSON: " . json_last_error_msg());
        }

        (new Storage)->put($filePath, $json);
    }
}
