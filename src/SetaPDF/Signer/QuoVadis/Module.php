<?php
/**
 * This file is part of the demo pacakge of the SetaPDF-Signer Component
 *
 * @copyright  Copyright (c) 2015 Setasign - Jan Slabon (http://www.setasign.com)
 * @category   SetaPDF
 * @package    SetaPDF_Signer
 * @license    http://www.apache.org/licenses/LICENSE-2.0
 * @version    $Id$
 */

/**
 * Signature module class for the QuoVadis Signing and Validation Service
 *
 * @link https://www.quovadisglobal.ch/Dienstleistungen/SigningServices/Signierungs-%20und%20Validierungsservice.aspx?sc_lang=en-GB
 * @copyright  Copyright (c) 2015 Setasign - Jan Slabon (http://www.setasign.com)
 * @category   SetaPDF
 * @package    SetaPDF_Signer
 * @license    http://www.apache.org/licenses/LICENSE-2.0
 */
class SetaPDF_Signer_QuoVadis_Module extends SetaPDF_Signer_Timestamp_Module_AbstractModule implements
    SetaPDF_Signer_Signature_Module_ModuleInterface,
    SetaPDF_Signer_Timestamp_Module_ModuleInterface
{
    /**
     * The URI of the QuoVadis Signing and Validation Web Services
     *
     * @var string
     */
    protected $_wsdl;

    /**
     * The account ID is the unique name of the account specified on the server.
     *
     * @var string
     */
    protected $_accountId;

    /**
     * The password which secures the access to the account.
     *
     * @var string
     */
    protected $_password;

    /**
     * @var string
     */
    protected $_clientId;

    /**
     * The PIN code to activate the signing key
     *
     * @var string
     */
    protected $_pin;

    /**
     * The profile identifies the signature specifications by a unique name.
     *
     * @var string
     */
    protected $_profile;

    /**
     * The ticket received in the login sequence.
     *
     * @var string
     */
    protected $_ticket;

    /**
     * Client options that will be passed to the SoapClient constructor
     *
     * @var array
     */
    protected $_clientOptions = array();

    /**
     * Defines wheter verfication data should be collected during the signature process
     *
     * @var bool
     */
    protected $_collectVerificationData = false;

    /**
     * The verficiation data
     *
     * @var stdClass|null
     */
    protected $_verificationData;

    /**
     * The last result of a webservice call.
     *
     * @var stdClass
     */
    protected $_lastResult;

    /**
     * The constructor.
     *
     * @param string $wsdl
     * @param string $accountId
     * @param string $password
     * @param string $profile
     * @param array $clientOptions
     */
    public function __construct(
        $wsdl, $accountId, $password, $clientId, $pin, $profile = 'Default', array $clientOptions = array()
    )
    {
        $this->_wsdl = $wsdl;
        $this->_accountId = $accountId;
        $this->_password = $password;
        $this->_clientId = $clientId;
        $this->_pin = $pin;
        $this->_profile = $profile;
        $this->_clientOptions = $clientOptions;
    }

    /**
     * Ensures a logout from the QuoVadis system.
     *
     * @throws SetaPDF_Signer_Exception
     */
    public function __destruct()
    {
        $this->logout();
    }

    /**
     * Get the last result.
     *
     * @return stdClass
     */
    public function getLastResult()
    {
        return $this->_lastResult;
    }

    /**
     * Get the current used ticket id.
     *
     * @return string
     * @throws BadMethodCallException
     */
    public function getTicket()
    {
        if (!$this->_ticket) {
            throw new BadMethodCallException('No ticket available. You need to login first.');
        }

        return $this->_ticket;
    }

    /**
     * Sets wherther verification data should be collected or not.
     *
     * @param bool $collectVerificationData
     */
    public function setCollectVerificationData($collectVerificationData = true)
    {
        $this->_collectVerificationData = (boolean)$collectVerificationData;
    }

    /**
     * Get the verification data.
     *
     * @return null|stdClass
     */
    public function getVerificationData()
    {
        return $this->_verificationData;
    }

    /**
     * Create the signature.
     *
     * @param SetaPDF_Core_Reader_FilePath|string $tmpPath
     * @return mixed
     * @throws SetaPDF_Signer_Exception
     */
    public function createSignature($tmpPath)
    {
        if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
            throw new InvalidArgumentException('Signature template file cannot be read.');
        }

        $hash = $this->_getHash($tmpPath);

        $req = array(
            'signingRequest' => array(
                'ticket' => $this->getTicket(),
                'pin' => $this->_pin,
                'data' => $hash,
            )
        );

        $client = new SoapClient($this->_wsdl, array_merge($this->_clientOptions, array('trace' => true)));

        $result = $client->signDigest($req);
        $this->_lastResult = $result;

        if ($result->return->result != 0) {
            throw new SetaPDF_Signer_Exception(
                sprintf('QuoVadis signDigest failed with result code %s.', $result->return->result)
            );
        }

        $signature = $result->return->signature;

        if ($this->_collectVerificationData) {
            $req = array(
                'verifyingRequest' => array(
                    'ticket' => $this->getTicket(),
                    'data' => $hash,
                    'signature' => $signature,
                )
            );

            $verificationData = $client->verifyDigest($req)->return;

            if ($verificationData->result != 0) {
                throw new SetaPDF_Signer_Exception(
                    sprintf('QuoVadis verifyDigest failed with result code %s.', $verificationData->result)
                );
            }

            $this->_verificationData = $verificationData;
        }

        return $signature;
    }

    /**
     * Create a timestamp signature.
     *
     * @param string|SetaPDF_Core_Reader_FilePath $data
     * @return SetaPDF_Signer_Asn1_Element
     * @throws SetaPDF_Signer_Exception
     */
    public function createTimestamp($data)
    {
        $hash = $this->_getHash($data);
        $req = array(
            'timestampingRequest' => array(
                'ticket' => $this->getTicket(),
                'data' => $hash
            )
        );

        $client = new SoapClient($this->_wsdl, array_merge($this->_clientOptions, array('trace' => true)));

        $result = $client->timestampDigest($req);
        $this->_lastResult = $result;

        if ($result->return->result != 0) {
            throw new SetaPDF_Signer_Exception(
                sprintf('QuoVadis timestamp failed with result code %s.', $result->return->result)
            );
        }

        $timestampToken = $result->return->timestampToken;

        if ($this->_collectVerificationData) {
            $req = array(
                'verifyingRequest' => array(
                    'ticket' => $this->getTicket(),
                    'data' => $hash,
                    'signature' => $timestampToken
                )
            );

            $verificationData = $client->verifyTimestamp($req)->return;

            if ($verificationData->result != 0) {
                throw new SetaPDF_Signer_Exception(
                    sprintf('QuoVadis verifyTimestamp failed with result code %s.', $verificationData->result)
                );
            }

            $this->_verificationData = $verificationData;
        }

        return $timestampToken;
    }

    /**
     * Login to QuoVadis Service.
     *
     * @return bool
     * @throws SetaPDF_Signer_Exception
     */
    public function login()
    {
        $client = new SoapClient($this->_wsdl, array_merge($this->_clientOptions, array('trace' => true)));
        $req = array(
            'loginRequest' => array(
                'accountId' => $this->_accountId,
                'secret' => $this->_password,
                'clientId' => $this->_clientId,
                'profile' => $this->_profile
            )
        );

        $result = $client->login($req);
        $this->_lastResult = $result;

        if ($result->return->result != 0) {
            throw new SetaPDF_Signer_Exception(
                sprintf('QuoVadis login failed with result code %s.', $result->return->result)
            );
        }

        $this->_ticket = $result->return->ticket;

        return true;
    }

    /**
     * Logout from QuoVadis Service.
     *
     * @return bool
     * @throws SetaPDF_Signer_Exception
     */
    public function logout()
    {
        if (!$this->_ticket) {
            return false;
        }

        $client = new SoapClient($this->_wsdl, array_merge($this->_clientOptions, array('trace' => true)));
        $result = $client->logout(array(
            'logoutRequest' => array(
                'ticket' => $this->_ticket
            )
        ));

        $this->_lastResult = $result;

        if ($result->return->result != 0) {
            throw new SetaPDF_Signer_Exception(
                sprintf('QuoVadis logout failed with result code %s.', $result->return->result)
            );
        }

        return true;
    }

    /**
     * Updates the DSS with verification data received during the signature process.
     *
     * @param SetaPDF_Core_Document $document
     * @param $fieldName
     * @throws SetaPDF_Exception_NotImplemented
     */
    public function updateDss(SetaPDF_Core_Document $document, $fieldName)
    {
        throw new SetaPDF_Exception_NotImplemented(
            'This method is implemented but currently not available.'
        );

        if ($this->_collectVerificationData == false || !$this->_verificationData) {
            throw new BadMethodCallException('No verification data collected.');
        }

        $ocsps = array();
        $certificates = array();
        $crls = array();

        $data = $this->_verificationData;

        $certificates[] = $data->signatureInfos->basicInfo->signerCertificate;

        if (isset($data->signatureInfos->basicInfo->basicOcspResponse)) {
            $ocsps[] = $data->signatureInfos->basicInfo->basicOcspResponse;
        }

        $chain = $data->signatureInfos->chain;
        foreach ($chain AS $cert) {
            $certificates[] = $cert->encoded;
        }

        $revocationInfo = $data->signatureInfos->revocationInfo;
        if (isset($revocationInfo->crlInfo)) {
            $crls[] = $revocationInfo->crlInfo->encoded;
        }

        if (isset($revocationInfo->basicOcspInfo)) {
            // file_put_contents('ocsp-before.dat', $revocationInfo->basicOcspInfo->encoded);
            $ocsp  = $this->_prepareOscpResponse($revocationInfo->basicOcspInfo->encoded);
            // file_put_contents('ocsp-after.dat', $ocsp);
            $ocsps[] = $ocsp;

            $certificates[] = $revocationInfo->basicOcspInfo->endCertificate;
            $certificates[] = $revocationInfo->basicOcspInfo->issuerCertificate;
        }

        if (isset($data->timestampInfo)) {
            $certificates[] = $data->timestampInfo->signerCertificate;
        }

        $dss = new SetaPDF_Signer_DocumentSecurityStore($document);
        $dss->addValidationRelatedInfoByField($fieldName, $crls, $ocsps, $certificates);
    }

    /**
     * Encapsulates an OCSP response value in a response envelope.
     *
     * @param string $encoded
     * @return string
     * @throws SetaPDF_Signer_Asn1_Exception
     */
    protected function _prepareOscpResponse($encoded)
    {
        $main = SetaPDF_Signer_Asn1_Element::parse($encoded);

        $final = new SetaPDF_Signer_Asn1_Element(SetaPDF_Signer_Asn1_Element::SEQUENCE, '', array(
            new SetaPDF_Signer_Asn1_Element(SetaPDF_Signer_Asn1_Element::ENUMERATED, "\0"),
            new SetaPDF_Signer_Asn1_Element(
                SetaPDF_Signer_Asn1_Element::SEQUENCE | SetaPDF_Signer_Asn1_Element::TAG_CLASS_CONTEXT_SPECIFIC,
                '',
                array(
                    new SetaPDF_Signer_Asn1_Element(SetaPDF_Signer_Asn1_Element::SEQUENCE, '', array(
                        new SetaPDF_Signer_Asn1_Element(
                            SetaPDF_Signer_Asn1_Element::OBJECT_IDENTIFIER,
                            SetaPDF_Signer_Asn1_Oid::encode('1.3.6.1.5.5.7.48.1.1')
                        ),
                        new SetaPDF_Signer_Asn1_Element(SetaPDF_Signer_Asn1_Element::OCTET_STRING, $main)
                    ))
                )
            )
        ));

        return (string)$final;
    }
}