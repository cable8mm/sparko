import json
import urllib.error
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

if not secret or not channel:
    raise SystemExit('PORTONE_API_SECRET and PORTONE_E2E_CHANNEL_KEY are required.')

headers = {'Authorization': 'PortOne ' + secret, 'Content-Type': 'application/json'}


def request(method, path, body=None):
    payload = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(base + path, data=payload, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=30) as response:
            return response.status, json.loads(response.read().decode())
    except urllib.error.HTTPError as error:
        return error.code, json.loads(error.read().decode() or b'{}')


def issue(customer_id):
    status, data = request('POST', '/billing-keys', {
        'channelKey': channel,
        'customer': {
            'id': customer_id,
            'name': {'full': '포트원'},
            'phoneNumber': '010-0000-1234',
            'email': 'test@portone.io',
        },
        'method': {'card': {'credential': {
            'number': '4673090000000032',
            'expiryYear': '30',
            'expiryMonth': '12',
            'birthOrBusinessRegistrationNumber': '900101',
            'passwordTwoDigits': '00',
        }}},
    })
    if status not in (200, 201):
        raise SystemExit(f'issue failed: {status} {data}')
    return data['billingKeyInfo']['billingKey']


def lookup(key):
    status, data = request('GET', '/billing-keys/' + urllib.parse.quote(key))
    return status, data.get('status'), data.get('code')


customer = 'sparko-replace-' + uuid.uuid4().hex[:10]
old_key = issue(customer + '-old')
old_status = lookup(old_key)

new_key = issue(customer + '-new')
new_status = lookup(new_key)

delete_status, delete_body = request('DELETE', '/billing-keys/' + urllib.parse.quote(old_key))
old_after_delete = lookup(old_key)

cleanup_status, _ = request('DELETE', '/billing-keys/' + urllib.parse.quote(new_key))

result = {
    'old_key_status_before': old_status[1],
    'new_key_status_before': new_status[1],
    'delete_http_status': delete_status,
    'delete_error_code': delete_body.get('code'),
    'old_key_status_after': old_after_delete[1],
    'old_lookup_http_status_after': old_after_delete[0],
    'cleanup_http_status': cleanup_status,
}
print(json.dumps(result, indent=2, ensure_ascii=False))
