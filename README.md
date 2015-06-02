# SetaPDF-Signer component signature module for the QuoVadis Signing and Validation Service.

This package offers an individual module for the [SetaPDF-Signer Component](https://www.setasign.com/signer) that allows
you to use the [QuoVadis Signing and Validation Service](https://www.quovadisglobal.ch/Dienstleistungen/SigningServices/Signierungs-%20und%20Validierungsservice.aspx?sc_lang=en-GB) 
for the signature process of PDF documents. A big advantage of this module is, that it only transfers the hash, that 
should be signed, to the QuoVadis Signing and Validation Service and not the complete PDF document. The returned 
signature will be placed in the PDF document by the SetaPDF-Signer Component.

## Installation
Add following to your composer.json:

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "http://www.setasign.com/downloads/"
        }
    ],
    "require": {
        "setasign/setapdf-signer-addon-quovadis": "1.*"
    }
}
```

By default this packages depends on a licensed version of the SetaPDF-Signer component. If you want to use it with an [evaluation version](https://www.setasign.com/products/setapdf-signer/evaluate/) please use following in your composer.json:

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "http://www.setasign.com/downloads/"
        }
    ],
    "require": {
        "setasign/setapdf-signer-addon-quovadis": "dev-evaluation"
    }
}
```
