#!/usr/bin/env python3
import argparse
import csv
import os
import re
import subprocess
import sys
import time
from html.parser import HTMLParser
from urllib.parse import urlparse
from urllib.request import Request, urlopen

requests = None


def load_requests():
    global requests
    if requests is None:
        import warnings
        with warnings.catch_warnings():
            warnings.simplefilter('ignore')
            import requests
    return requests

VK_RE = re.compile(r'(?:https?://)?(?:[a-z0-9-]+\.)*vk\.(?:com|ru)/([^"\'<>\s?#]+)', re.I)
ID_RE = re.compile(r'/(id\d{4,})', re.I)
HANDLE_OK = re.compile(r'^[A-Za-z0-9_.-]{1,32}$')
SHORTNAME_OK = re.compile(r'^[a-z][a-z0-9_.]{1,31}$')
GROUP_RE = re.compile(r'^(?:club|public|event)\d+$', re.I)
API_VERSION = '5.199'

SERVICE_PREFIXES = (
    'album', 'al', 'app', 'artist', 'audio', 'av', 'board', 'bookmarks', 'clip', 'clips',
    'club', 'dev', 'discover_search', 'doc', 'docs', 'event', 'fave', 'feed', 'friends',
    'games', 'gift', 'groups', 'help', 'hq', 'im', 'job', 'join', 'login',
    'mail', 'market', 'mem', 'menu', 'messages', 'music', 'notes', 'noti', 'offset', 'page',
    'photo', 'photos', 'place', 'polls', 'privacy', 'react', 'reactions', 'search',
    'services', 'settings', 'sticker', 'support', 't', 'terms', 'topic', 'ui', 'video',
    'videos', 'vkui', 'voice', 'wall', 'watch', 'write', 'away', 'attach', 'amplify',
    'about', 'policy', 'cookie', 'audios', 'mood',
)


class LinkParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.current_href = None
        self.buf = []
        self.anchors = []

    def handle_starttag(self, tag, attrs):
        if tag == 'a':
            d = dict(attrs)
            self.current_href = d.get('href')
            self.buf = []
        elif tag in ('br', 'div'):
            self.buf.append(' ')

    def handle_endtag(self, tag):
        if tag == 'a':
            text = ' '.join(''.join(self.buf).split())
            if self.current_href:
                self.anchors.append((self.current_href, text))
            self.current_href = None
            self.buf = []

    def handle_data(self, data):
        if self.current_href is not None:
            self.buf.append(data)


def normalize(raw):
    raw = (raw or '').strip()
    if not raw:
        return None
    if raw.startswith('//'):
        raw = 'https:' + raw
    if raw.startswith('/'):
        path = raw
    else:
        if not re.match(r'https?://', raw, re.I):
            raw = 'https://' + raw
        try:
            u = urlparse(raw)
        except ValueError:
            return None
        if 'vk' not in u.netloc.lower():
            return None
        path = u.path
    if re.search(r'\.(?:css|js|png|jpe?g|svg|gif|ico|woff2?|json|map)$', path, re.I):
        return None
    parts = [p for p in path.split('/') if p]
    if not parts:
        return None
    handle = parts[0]
    if not HANDLE_OK.match(handle):
        return None
    return 'https://vk.com/' + handle, handle


def classify(handle):
    low = handle.lower().rstrip('/')
    if re.match(r'^id\d{4,}$', low):
        return 'profile'
    if GROUP_RE.match(low):
        return 'group'
    if low.startswith('wall-') or low.startswith('photo') or low.startswith('video'):
        return 'other'
    for p in SERVICE_PREFIXES:
        if low == p or low.startswith(p + '_') or low.startswith(p + '-') or \
           re.match(re.escape(p) + r'\d', low):
            return 'other'
    if re.match(r'^[a-z][a-z0-9_.-]{0,31}$', low):
        return 'profile'
    return 'other'


GROUP_NAME_WORDS = (
    'сообщество', 'группа', 'студи', 'бар', 'паб', 'клуб', 'кафе', 'кофе',
    'лофт', 'фестивал', 'площадк', 'проект', 'центр', 'школ', 'school',
    'studio', 'академ', 'лейбл',
)


def add_seen(seen, url, handle, name):
    key = handle.lower().rstrip('/')
    if not key:
        return
    rec = seen.setdefault(key, {'url': url, 'name': '', 'count': 0, 'cls': classify(key)})
    rec['count'] += 1
    if name and not rec['name']:
        rec['name'] = name
        if rec['cls'] == 'profile' and not re.match(r'^id\d', key):
            low = name.lower()
            if any(w in low for w in GROUP_NAME_WORDS):
                rec['cls'] = 'group'


def extract_urls(urls):
    seen = {}
    for u in urls:
        n = normalize(u)
        if n:
            add_seen(seen, n[0], n[1], '')
    return seen


def extract(html):
    seen = {}
    parser = LinkParser()
    try:
        parser.feed(html)
    except Exception:
        pass
    for raw, text in parser.anchors:
        n = normalize(raw)
        if n:
            add_seen(seen, n[0], n[1], text)

    for m in VK_RE.findall(html):
        h = m.lower().strip()
        if HANDLE_OK.match(h):
            add_seen(seen, 'https://vk.com/' + h, h, '')
    for m in ID_RE.findall(html):
        h = m.lower()
        add_seen(seen, 'https://vk.com/' + h, h, '')
    for m in re.finditer(r'\[(id|club|public|event)(\d+)\|([^\]]*)\]', html, re.I):
        pfx, num, name = m.group(1).lower(), m.group(2), m.group(3).strip()
        h = pfx + num
        add_seen(seen, 'https://vk.com/' + h, h, name)
    return seen


MUSIC_WORDS = (
    'музык', 'музыкант', 'гитар', 'вокал', 'dj', 'диджей', 'рок', 'рэп', 'реп',
    'песн', 'певец', 'певиц', 'групп', 'band', 'music', 'саунд', 'sound',
    'студи', 'studio', 'кавер', 'ирландск', 'бузуки', 'барабан', 'перкусси',
    'соул', 'techno', 'дримх', 'ритм', 'пою', 'стих', 'электрон', 'акустик',
    'трек', 'сет', 'сетов', 'звук', 'фестивал', 'концерт', 'квартирник',
    'open mic', 'хаус', 'techno', 'hard', 'сцен', 'репетиц', 'cover', 'кавер',
)

HTML_EXT = ('.html', '.htm')


def detect_music(html, key, label):
    label = label or ''
    low_label = label.lower()
    if any(w in low_label for w in MUSIC_WORDS):
        return True
    idx = -1
    m = re.search(r'vk\.(?:com|ru)/' + re.escape(key) + r'(?=[?"\'#&\s<])', html, re.I)
    if m:
        idx = m.start()
    else:
        m = re.search(r'["\']/?' + re.escape(key) + r'["\']', html, re.I)
        if m:
            idx = m.start()
    if idx < 0:
        return False
    win = html[max(0, idx - 200): idx + 200].lower()
    return any(w in win for w in MUSIC_WORDS)


PDF_EXT = ('.pdf',)


def pdf_extract(path):
    with open(path, 'rb') as f:
        data = f.read()
    uris = []
    for m in re.finditer(rb'/URI\s*\(([^)]*)\)', data):
        u = m.group(1)
        u = re.sub(rb'\\\(', b'(', u)
        u = re.sub(rb'\\\)', b')', u)
        u = re.sub(rb'\\\\', lambda m: b'\\', u)
        try:
            uris.append(u.decode('utf-8', 'replace'))
        except Exception:
            pass
    for m in re.finditer(rb'/URI\s*<([0-9A-Fa-f]+)>', data):
        try:
            uris.append(bytes.fromhex(m.group(1).decode()).decode('utf-8', 'replace'))
        except Exception:
            pass
    text = ''
    helper = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'pdf_text.swift')
    try:
        r = subprocess.run(['swift', helper, path], capture_output=True, timeout=180)
        text = r.stdout.decode('utf-8', 'replace')
    except Exception:
        text = ''
    return uris, text


def merge_sub(seen, sub, fname):
    for k, rec in sub.items():
        if k in seen:
            seen[k]['count'] += rec['count']
            seen[k]['files'].add(fname)
            if rec.get('name') and not seen[k]['name']:
                seen[k]['name'] = rec['name']
            seen[k]['music'] = seen[k]['music'] or rec.get('music', False)
        else:
            rec['files'] = {fname}
            seen[k] = dict(rec)


def scan_folder(folder):
    os.makedirs(folder, exist_ok=True)
    seen = {}
    files = []
    for fname in sorted(os.listdir(folder)):
        low = fname.lower()
        path = os.path.join(folder, fname)
        if low.endswith(HTML_EXT):
            files.append(fname)
            with open(path, 'r', encoding='utf-8', errors='replace') as f:
                html = f.read()
            sub = extract(html)
            for k, rec in sub.items():
                rec['music'] = detect_music(html, k, rec.get('name', ''))
        elif low.endswith(PDF_EXT):
            files.append(fname)
            uris, text = pdf_extract(path)
            doc = text + ' | ' + ' '.join(uris)
            sub = extract_urls(uris)
            for k, rec in sub.items():
                rec['music'] = detect_music(doc, k, rec.get('name', ''))
        else:
            continue
        merge_sub(seen, sub, fname)
    for rec in seen.values():
        rec['files'] = sorted(rec['files'])
    return seen, files


ROLE_RULES = {
    'вокалист': ('вокал', 'пою', 'поёт', 'поет', 'певец', 'певица', 'голос',
                 'автор-исполнител', 'singer', 'исполняю песни'),
    'гитарист': ('гитар', 'guitar', 'акустик', 'струн'),
    'барабанщик/перкуссионист': ('барабан', 'ударн', 'перкусси', 'кахон', 'drums'),
    'басист': ('бас-гитар', 'басист', 'bass'),
    'клавишник': ('клавиш', 'пианино', 'фортепиано', 'синтезатор', 'piano'),
    'диджей/электроника': ('диджей', 'dj', 'электрон', 'techno', 'хаус', 'битмейкер',
                           'house', 'hypnotic', 'hard techno', 'сет'),
    'рэпер': ('рэп', 'реп', 'рэпер', 'хип-хоп', 'хипхоп', 'freestyle', 'битмейкер'),
    'звукорежиссёр/продюсер': ('звукорежиссёр', 'звукорежиссер', 'продюсер', 'сведение',
                               'мастеринг', 'саунд', 'звукозапис'),
    'автор песен/стихов': ('автор песен', 'пишу песни', 'стих', 'поэт', 'поэз', 'тексты песен'),
    'музыкант (общее)': ('музыкант', 'музык', 'музициру', 'исполнител', 'артист', 'music'),
}

COMM_RULES = {
    'музыкальное сообщество': ('музыкальное сообщество', 'афиша', 'концерт',
                               'open mic', 'квартирник', 'фестивал', 'лейбл', 'музыкант',
                               'релиз', 'трек', 'клип', 'артист', 'плейлист', 'вокальн', 'гитарн'),
    'бар / кафе / площадка': ('бар', 'паб', 'pub', 'клуб', 'лофт', 'площадк', 'кафе',
                              'кофейн', 'ресторан', 'коктейл', 'speak', 'веранда', 'танцпол'),
    'студия': ('студи', 'репетиционн', 'звукозапис', 'sound', 'studio', 'сведение'),
    'вечеринки/мероприятия': ('вечеринк', 'party', 'ивент', 'event', 'тусовк', 'ночь',
                              'line up', 'lineup'),
}


def match_rules(rules, text):
    low = text.lower()
    found = []
    for label, words in rules.items():
        hits = [w for w in words if w in low]
        if hits:
            found.append((label, hits))
    return found


def analyze_page(html, urls=None):
    low = html.lower()
    roles = match_rules(ROLE_RULES, low)
    comm = match_rules(COMM_RULES, low)
    seen = extract(html)
    if urls:
        for u in urls:
            n = normalize(u)
            if n:
                add_seen(seen, n[0], n[1], '')
    groups = [(rec['url'], rec.get('name', '') or '',
               detect_music(html, key, rec.get('name', '')))
              for key, rec in seen.items() if rec['cls'] == 'group']
    profiles = sum(1 for rec in seen.values() if rec['cls'] == 'profile')
    counts = {}
    for key, rec in seen.items():
        if rec['cls'] in ('profile', 'group'):
            counts[key] = counts.get(key, 0) + rec['count']
    self_key = max(counts, key=counts.get) if counts else None
    self_is_group = bool(self_key and classify(self_key) == 'group')
    return {
        'roles': roles,
        'comm': comm,
        'groups': groups,
        'profiles': profiles,
        'self_key': self_key,
        'self_is_group': self_is_group,
    }


GENERIC_ROLE_LABELS = {'музыкант (общее)'}


def print_analysis(fname, a):
    print('===== ' + fname + ' =====')
    strong = [(l, h) for l, h in a['roles'] if l not in GENERIC_ROLE_LABELS]
    if a['self_is_group']:
        print('Похоже, страница ГРУППЫ/СООБЩЕСТВА (сама ссылка: ' + a['self_key'] + ').')
        if strong:
            print('   Возможный состав/специализация по тексту:')
            for label, hits in strong:
                print('   - ' + label + '  (' + ', '.join(hits) + ')')
    elif strong:
        print('Похоже, личная страница МУЗЫКАНТА:')
        for label, hits in strong:
            print('   - ' + label + '  (' + ', '.join(hits) + ')')
        if a['comm']:
            print('При этом на странице есть признаки сообщества/площадки:')
            for label, hits in a['comm']:
                print('   - ' + label + '  (' + ', '.join(hits) + ')')
    elif a['comm']:
        print('Похоже на сообщество/площадку:')
        for label, hits in a['comm']:
            print('   - ' + label + '  (' + ', '.join(hits) + ')')
    elif a['roles']:
        print('Возможно, страница музыканта (общие слова):')
        for label, hits in a['roles']:
            print('   - ' + label + '  (' + ', '.join(hits) + ')')
    else:
        print('Музыкальных признаков не найдено (проверь вручную).')
    print('   Профилей на странице: ' + str(a['profiles']))
    if a['groups']:
        print('   Сообщества/группы на странице:')
        for url, name, music in a['groups']:
            m = ' [музыка?]' if music else ''
            nm = (': ' + name) if name else ''
            print('      ' + url + m + nm)
    else:
        print('   Сообществ/групп на странице не найдено.')
    print()


def fetch(url, cookie):
    headers = {'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36'}
    if cookie:
        headers['Cookie'] = cookie
    req = Request(url, headers=headers)
    with urlopen(req, timeout=30) as r:
        data = r.read()
    for enc in ('utf-8', 'cp1251', 'latin-1'):
        try:
            return data.decode(enc)
        except UnicodeDecodeError:
            continue
    return data.decode('utf-8', errors='replace')


def url_handle(u):
    n = normalize(u)
    return n[1].lower().rstrip('/') if n else None


def load_contacts(path):
    contacts = {}
    if not os.path.exists(path):
        return contacts
    with open(path, newline='', encoding='utf-8') as f:
        for row in csv.DictReader(f, delimiter=';'):
            if not row.get('vk_url'):
                continue
            h = url_handle(row['vk_url'])
            if h:
                contacts[h] = row
    return contacts


def get_api_token(path):
    if os.path.exists(path):
        t = open(path, encoding='utf-8').read().strip()
        if t:
            return t
    return ''


def set_api_token(path, token):
    if not token:
        token = input('Вставь access_token и нажми Enter: ').strip()
    if not token:
        sys.exit('Токен пустой, не сохранён.')
    with open(path, 'w', encoding='utf-8') as f:
        f.write(token)
    print('Токен сохранён в', path)


def api_call(session, method, token, **kwargs):
    kwargs['v'] = API_VERSION
    kwargs['access_token'] = token
    r = session.post('https://api.vk.com/method/' + method, data=kwargs, timeout=40)
    j = r.json()
    if 'error' in j:
        e = j['error']
        code = e.get('error_code')
        if code == 5:
            print('ОШИБКА 5: ключ доступа не действителен.')
            print('Получи новый ключ ВКонтакте и введи его ещё раз:')
            print(' python3 vk_links.py --set-token')
            sys.exit(1)
        if code in (15, 18, 30, 203, 100):
            return None
        raise RuntimeError('VK API ошибка %s: %s' % (code, e.get('error_msg')))
    return j.get('response')


def api_collect(session, token, handle, max_people=300, max_wall=100):
    seen = {}

    def add(obj):
        if not obj:
            return
        if obj.get('type') in ('group', 'public', 'page', 'event'):
            gtype = 'club' if obj.get('type') == 'group' else obj.get('type')
            iid = abs(int(obj.get('id') or 0))
            dom = obj.get('screen_name') or ''
            url = ('https://vk.com/' + dom) if dom else ('https://vk.com/' + gtype + str(iid))
            name = obj.get('name', '')
        else:
            iid = obj.get('id')
            if iid is None:
                return
            dom = obj.get('domain') or ''
            url = ('https://vk.com/' + dom) if dom else ('https://vk.com/id' + str(iid))
            name = (obj.get('first_name') or '') + ' ' + (obj.get('last_name') or '')
        n = normalize(url)
        if not n:
            return
        key = n[1].lower().rstrip('/')
        rec = seen.setdefault(key, {'url': n[0], 'name': '', 'count': 0, 'cls': classify(key)})
        rec['count'] += 1
        if name.strip() and not rec['name']:
            rec['name'] = name.strip()

    m = GROUP_RE.match(handle)
    if m:
        gid = int(re.sub(r'\D', '', handle))
        method, keyw = 'groups.getMembers', {'group_id': -abs(gid), 'fields': 'domain'}
    else:
        r = api_call(session, 'users.get', token, user_ids=handle, fields='domain')
        if not r:
            print('Не удалось найти пользователя по ссылке: ' + handle)
            return {}
        uid = r[0]['id']
        method, keyw = 'users.getFollowers', {'user_id': uid, 'fields': 'domain'}

    offset = 0
    skipped = 0
    while offset < max_people:
        params = dict(keyw)
        params['count'] = 1000
        params['offset'] = offset
        resp = api_call(session, method, token, **params)
        if not resp:
            break
        items = resp.get('items') or []
        for obj in items:
            add(obj)
        n_items = len(items)
        skipped += n_items
        if n_items < 1000:
            break
        offset += 1000
        time.sleep(0.3)

    if not m:
        subs = api_call(session, 'users.getSubscriptions', token, user_id=uid, extended=1, count=200)
        if subs:
            sub_items = subs.get('items') or {}
            for obj in sub_items.get('users') or []:
                add(obj)
            for obj in sub_items.get('groups') or []:
                add(obj)

        wall = api_call(session, 'wall.get', token, owner_id=uid, count=max_wall, extended=1)
        mention_ids = []
        if wall:
            for obj in wall.get('profiles') or []:
                add(obj)
            for obj in wall.get('groups') or []:
                add(obj)
            for post in wall.get('items') or []:
                text = post.get('text') or ''
                for mm in re.finditer(r'\[(?:id|club)(\d+)\|', text):
                    if mm.group(1).isdigit():
                        mention_ids.append(int(mm.group(1)))
        if mention_ids:
            mention_ids = sorted(set(mention_ids))
            for i in range(0, len(mention_ids), 100):
                ids = ','.join(str(x) for x in mention_ids[i:i + 100])
                rr = api_call(session, 'users.get', token, user_ids=ids, fields='domain')
                if rr:
                    for obj in rr:
                        add(obj)
                time.sleep(0.3)

    return seen


def build_rows(seen, contacts, only_new, music_only=False):
    rows = []
    for key in seen:
        rec = seen[key]
        c = contacts.get(key)
        if c:
            note = (c.get('заметка') or '').strip()
            contacted = (c.get('контактирован') or '').strip() in ('1', 'да', 'yes', 'true')
        else:
            note = ''
            contacted = False
        if only_new and contacted:
            continue
        if music_only and not rec.get('music'):
            continue
        rows.append({
            'key': key,
            'cls': rec['cls'],
            'name': rec.get('name', '') or '',
            'url': rec['url'],
            'music': 'да' if rec.get('music') else '',
            'files': rec.get('files', []),
            'note': note,
            'contacted': '1' if contacted else '',
        })
    rows.sort(key=lambda r: (not r['name'], r['name'].lower(), r['key']))
    return rows


def print_rows(rows, out):
    sections = {'profile': 'ПРОФИЛИ (люди)', 'group': 'ГРУППЫ', 'other': 'ДРУГИЕ ССЫЛКИ VK'}
    n = 0
    for cls in ('profile', 'group', 'other'):
        part = [r for r in rows if r['cls'] == cls]
        if not part:
            continue
        print('\n=== ' + sections[cls] + ' ===')
        for r in part:
            n += 1
            name = r['name'] if r['name'] else '—'
            music = ' [музыка?]' if r['music'] else ''
            mark = ' [УЖЕ КОНТАКТИРОВАЛ]' if r['contacted'] else ''
            note = (': ' + r['note']) if r['note'] else ''
            files = (f' (в {len(r["files"])} файлах)') if len(r['files']) > 1 else ''
            print(f'{n:3}. {name}{music}{mark} — {r["url"]}{files}{note}')

    with open(out, 'w', newline='', encoding='utf-8-sig') as f:
        w = csv.writer(f, delimiter=';')
        w.writerow(['тип', 'имя', 'ссылка', 'музыка', 'файлов', 'заметка', 'контактирован'])
        for r in rows:
            w.writerow([r['cls'], r['name'], r['url'], r['music'], len(r['files']), r['note'], r['contacted']])
    print(f'\nСохранено в: {out}  (всего строк: {len(rows)})')


def read_document(path):
    if path.lower().endswith(PDF_EXT):
        uris, text = pdf_extract(path)
        doc = text + ' | ' + ' '.join(uris)
        return doc, uris
    with open(path, 'r', encoding='utf-8', errors='replace') as f:
        return f.read(), None


def main():
    ap = argparse.ArgumentParser(
        description='Собирает ссылки на профили VK со страницы музыканта. '
                    'Работает через официальный API VK (автоматически, без сохранения страниц) '
                    'или по сохранённому html-файлу.')
    ap.add_argument('target', nargs='?',
                    help='URL вида https://vk.com/... или путь к сохранённому .html файлу')
    ap.add_argument('--folder', default='html',
                    help='Папка «бабка» с сохранёнными страницами (по умолчанию html)')
    ap.add_argument('--music', action='store_true',
                    help='Показывать только ссылки, рядом с которыми встречаются музыкальные слова')
    ap.add_argument('--roles', action='store_true',
                    help='Проанализировать сохранённые страницы: кто такой (вокалист/гитарист/...) '
                         'и что это за страница (сообщество/бар/студия)')
    ap.add_argument('--set-token', nargs='?', const='YES', metavar='TOKEN',
                    help='Сохранить ключ доступа (или без значения — вставишь из буфера) и выйти')
    ap.add_argument('--token-file', default='vk_token.txt',
                    help='Файл с ключом доступа (по умолчанию vk_token.txt)')
    ap.add_argument('--api', action='store_true', help='Принудительно собирать через API VK')
    ap.add_argument('--html', action='store_true', help='Принудительно парсить HTML/файл')
    ap.add_argument('--cookie', default='',
                    help='HTML-режим: необязательно, значение Cookie: из браузера в кавычках')
    ap.add_argument('--contacts', default='contacts.csv',
                    help='Файл с базой контактов (кто уже в деле)')
    ap.add_argument('--out', default='vk_links.csv', help='Куда сохранить результат')
    ap.add_argument('--only-new', action='store_true',
                    help='Показать только тех, кого ещё не контактировал')
    ap.add_argument('--max', type=int, default=300,
                    help='API: сколько подписчиков/участников максимум (по умолчанию 300)')
    args = ap.parse_args()

    if args.set_token is not None:
        set_api_token(args.token_file, None if args.set_token == 'YES' else args.set_token)
        return

    if args.roles:
        if args.target and os.path.exists(args.target):
            doc, urls = read_document(args.target)
            print_analysis(os.path.basename(args.target), analyze_page(doc, urls))
            return
        _, files = scan_folder(args.folder)
        if not files:
            print('В папке «' + args.folder + '» пока нет файлов .html или .pdf.')
            return
        print(f'Анализирую {len(files)} страниц из папки «{args.folder}»:\n')
        for fname in files:
            doc, urls = read_document(os.path.join(args.folder, fname))
            print_analysis(fname, analyze_page(doc, urls))
        return

    token = get_api_token(args.token_file)
    contacts = load_contacts(args.contacts)
    rows = []

    if not args.target:
        seen, files = scan_folder(args.folder)
        if files:
            print(f'Найдено страниц в папке «{args.folder}»: {len(files)}')
            for fname in files:
                print('  - ' + fname)
            rows = build_rows(seen, contacts, args.only_new, args.music)
            print_rows(rows, args.out)
            return
        print('В папке «' + args.folder + '» пока нет файлов .html.')
        print()
        print('Как пользоваться (без ключа):')
        print(' 1) Открой страницу музыканта в браузере (зайди в ВК);')
        print(' 2) Ctrl+S → сохрани «Веб-страница, полностью» в папку: ' + os.path.abspath(args.folder))
        print(' 3) Запусти: python3 vk_links.py  (или: python3 vk_links.py --music)')
        print()
        print('Можно положить сразу несколько страниц — скрипт всё объединит и уберёт дубли.')
        if not token:
            print()
            print('Либо настрой ключ ВКонтакте, чтобы не сохранять страницы вообще:')
            print(' python3 vk_links.py --set-token')
        return

    if os.path.exists(args.target):
        doc, urls = read_document(args.target)
        if urls is not None:
            seen = extract_urls(urls)
        else:
            seen = extract(doc)
        for k in seen:
            seen[k]['files'] = [os.path.basename(args.target)]
            seen[k]['music'] = detect_music(doc, k, seen[k].get('name', ''))
        rows = build_rows(seen, contacts, args.only_new, args.music)
        print_rows(rows, args.out)
        return

    is_url = bool(re.match(r'https?://', args.target, re.I))
    if not is_url:
        sys.exit(f'Файл не найден: {args.target}')

    use_api = args.api or (token and not args.html)
    if use_api and not token:
        sys.exit('Ключ доступа не найден. Сначала: python3 vk_links.py --set-token')
    if use_api:
        handle = url_handle(args.target)
        if not handle:
            sys.exit('Не удалось распознать ссылку VK: ' + args.target)
        session = load_requests().Session()
        print('Собираю через API VK...')
        seen = api_collect(session, token, handle, args.max)
        for k in seen:
            seen[k]['files'] = []
            seen[k]['music'] = any(w in seen[k].get('name', '').lower() for w in MUSIC_WORDS)
        rows = build_rows(seen, contacts, args.only_new, args.music)
        if not rows:
            print('Ссылок не найдено (возможно, ключ не имеет прав или страница закрыта).')
        print_rows(rows, args.out)
        return

    print('Скачиваю страницу...')
    try:
        html = fetch(args.target, args.cookie)
    except Exception as e:
        sys.exit(f'Не удалось скачать: {e}')
    seen = extract(html)
    for k in seen:
        seen[k]['files'] = [args.target]
        seen[k]['music'] = detect_music(html, k, seen[k].get('name', ''))
    found = any(seen[k]['cls'] == 'profile' for k in seen)
    if not found:
        print('Предупреждение: ссылок на профили не найдено. Скорее всего ВК показал страницу входа.')
        print('Положи сохранённую страницу в папку ' + os.path.abspath(args.folder) + ' и запусти: python3 vk_links.py')
    rows = build_rows(seen, contacts, args.only_new, args.music)
    print_rows(rows, args.out)


if __name__ == '__main__':
    main()