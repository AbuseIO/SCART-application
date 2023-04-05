#Install certs

You receive a (filename.p12) file with password from INHOPE.

You copy this to the certs directory:

- cp (filename.p12) /scart/scartroot/plugin/abuse/scart/assests/certs/

You generate the crt.pem and key.pem file with:

- openssl pkcs12 -in (filename.p12) -nocerts -out key.pem -nodes
- openssl pkcs12 -in (filename.p12) -nokeys -out crt.pem

Then you update the /scart/scartroot/config/importexport/.env with the correct password in:

- ICCAM_SSLCERTPW
- ICCAM_SSLKEYPW

You can test the ICCAM interface with:

- docker exec scartcron_importexport php artisan abuse:iccamApi







