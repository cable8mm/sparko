import json
import urllib.parse
import urllib.request
import uuid
from pathlib import Path

ENV = {}
for line in Path('.env').read_text().splitlines():
    if '=' in line and not line.strip().startswith('#'):
        key, _, value = line.partition('=')
        ENV[key.strip()] = value.strip().strip('"')

secret = ENV.get('PORTONE_API_SECRET', '')
channel = ENV.get('PORTONE_E2E_CHANNEL_KEY', '')
base = ENV.get('PORTONE_BASE_URL', 'https://api.portone.io').rstrip('/')
headers = {'Authorization': 'PortOne ' + secret, 'Content-Type': 'application/json'}


def request(method, path, body=None):
    payload = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(base + path, data=payload, headers=headers, method=method)
    with urllib.request.urlopen(req, timeout=30) as response:
        return json.loads(response.read().decode())


customer = 'sparko-receipt-' + uuid.uuid4().hex[:10]
key = request('POST', '/billing-keys', {
    'channelKey': channel,
    'customer': {'id': customer, 'name': {'full': '포트원'}, 'phoneNumber': '010-0000-1234', 'email': 'test@portone.io'},
    'method': {'card': {'credential': {
        'number': '4673090000000032', 'expiryYear': '30', 'expiryMonth': '12',
        'birthOrBusinessRegistrationNumber': '900101', 'passwordTwoDigits': '00',
    }}},
})['billingKeyInfo']['billingKey']

payment_id = 'sparko-receipt-' + uuid.uuid4().hex[:16]
request('POST', '/payments/' + payment_id + '/billing-key', {
    'billingKey': key,
    'orderName': 'Receipt observation',
    'amount': {'total': 1000},
    'currency': 'KRW',
})
paid = request('GET', '/payments/' + payment_id)
receipt = paid.get('receiptUrl')

result = {
    'status': paid.get('status'),
    'receipt_url_present': isinstance(receipt, str) and receipt != '',
    'receipt_url_host': urllib.parse.urlparse(receipt).netloc if isinstance(receipt, str) else None,
    'receipt_url_scheme': urllib.parse.urlparse(receipt).scheme if isinstance(receipt, str) else None,
}
print(json.dumps(result, indent=2, ensure_ascii=False))

request('DELETE', '/billing-keys/' + urllib.parse.quote(key))
