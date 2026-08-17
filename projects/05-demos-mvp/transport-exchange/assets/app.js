let map;
let markersLayer;
let routeLayer;
let radiusCircle;

const state = {
    role: 'carrier',
    vehicleId: 1,
    cargoId: 1,
    radius: 500,
    sort: 'profit',
    useRoute: 0
};

document.addEventListener('DOMContentLoaded', () => {
    map = L.map('map', {
        attributionControl: false
    }).setView([55.7, 50.0], 4);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(map);
    L.control.attribution({ prefix: false })
        .addAttribution('© OpenStreetMap © CARTO')
        .addTo(map);

    markersLayer = L.layerGroup().addTo(map);
    routeLayer = L.layerGroup().addTo(map);

    document.querySelectorAll('[data-role]').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-role]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            state.role = this.dataset.role;
            document.getElementById('carrierFilters').style.display = state.role === 'carrier' ? 'block' : 'none';
            document.getElementById('cargoOwnerFilters').style.display = state.role === 'cargo_owner' ? 'block' : 'none';
            loadData();
        });
    });

    document.getElementById('searchBtn').addEventListener('click', loadData);
    document.getElementById('vehicleSelect').addEventListener('change', function () {
        state.vehicleId = this.value;
        loadData();
    });
    document.getElementById('cargoSelect').addEventListener('change', function () {
        state.cargoId = this.value;
        loadData();
    });
    document.getElementById('radius').addEventListener('change', function () {
        state.radius = this.value;
    });
    document.getElementById('bodyType').addEventListener('change', function () {
        filterVehicles(this.value);
    });
    document.getElementById('sort').addEventListener('change', function () {
        state.sort = this.value;
    });
    document.getElementById('useRoute').addEventListener('change', function () {
        state.useRoute = this.checked ? 1 : 0;
        loadData();
    });

    state.vehicleId = document.getElementById('vehicleSelect').value;
    state.cargoId = document.getElementById('cargoSelect').value;

    loadData();
});

function getSelectedVehicle() {
    return window.DEMO.vehicles.find(v => v.id == state.vehicleId);
}

function getSelectedCargo() {
    return window.DEMO.cargoes.find(c => c.id == state.cargoId);
}

function filterVehicles(bodyType) {
    const select = document.getElementById('vehicleSelect');
    const list = window.DEMO.vehicles.filter(v => !bodyType || v.body_type === bodyType);
    select.innerHTML = list.map(v =>
        `<option value="${v.id}">${v.plate} · ${v.body_type} · ${v.capacity_t} т</option>`).join('');
    if (list.length) {
        state.vehicleId = list[0].id;
        select.value = list[0].id;
    }
    loadData();
}

function loadData() {
    const params = new URLSearchParams({
        action: state.role === 'carrier' ? 'search_cargoes' : 'search_vehicles'
    });

    if (state.role === 'carrier') {
        params.set('vehicle_id', state.vehicleId);
        params.set('radius', state.radius);
        params.set('sort', state.sort);
        params.set('use_route', state.useRoute);
    } else {
        params.set('cargo_id', state.cargoId);
        params.set('radius', state.radius);
        params.set('sort', state.sort);
    }

    fetch('api.php?' + params.toString())
        .then(r => r.json())
        .then(data => {
            if (!Array.isArray(data)) {
                document.getElementById('results').innerHTML = '<div class="empty">Ошибка загрузки</div>';
                return;
            }
            renderResults(data);
            renderMap(data);
        });
}

function renderResults(data) {
    const container = document.getElementById('results');

    if (data.length === 0) {
        container.innerHTML = '<div class="empty">По заданным фильтрам ничего не найдено</div>';
        return;
    }

    container.innerHTML = data.map(item => {
        const isCargo = state.role === 'carrier';
        const title = isCargo ? item.title : (item.plate + ' · ' + item.body_type);
        const route = isCargo ? (item.load_address + ' → ' + item.unload_address) : item.base_address;
        const params = isCargo ? (item.weight_t + ' т · ' + item.volume_m3 + ' м³') : (item.capacity_t + ' т · ' + item.volume_m3 + ' м³');
        const body = isCargo ? item.body_types : item.body_type;
        const rate = isCargo ? 'Ставка: ' + item.rate + ' ₽' : 'Себестоимость: ' + item.cost_per_km + ' ₽/км';
        const econ = item.profit !== undefined ? `
            <div class="econ">
                <span>Расстояние: ${item.distance_km} км</span>
                <span>Затраты: ${item.cost} ₽</span>
                <span>Прибыль: ${item.profit} ₽ (${item.margin}%)</span>
            </div>` : '';
        const rating = `<span class="rating">★ ${item.owner_rating} · ${item.owner_verified ? '✓ верифицирован' : 'не верифицирован'}</span>`;
        const respondBtn = `<button class="respond" data-id="${item.id}">Откликнуться</button>`;

        return `
            <div class="card">
                <div class="card-header">
                    <h4>${title}</h4>
                    <span class="badge">${body}</span>
                </div>
                <div class="route">${route}</div>
                <div class="params">${params} · ${item.loading_methods}</div>
                <div class="rate">${rate}</div>
                ${econ}
                <div class="meta">${rating} ${respondBtn}</div>
            </div>`;
    }).join('');

    document.querySelectorAll('.respond').forEach(btn => {
        btn.addEventListener('click', respond);
    });
}

function renderMap(data) {
    markersLayer.clearLayers();
    routeLayer.clearLayers();
    if (radiusCircle) {
        map.removeLayer(radiusCircle);
        radiusCircle = null;
    }

    if (state.role === 'carrier') {
        const veh = getSelectedVehicle();
        if (!veh) return;

        if (!state.useRoute) {
            radiusCircle = L.circle([veh.base_lat, veh.base_lng], {
                radius: state.radius * 1000,
                color: '#2563eb',
                weight: 1,
                fillOpacity: 0.05
            }).addTo(map);
            markersLayer.addLayer(L.marker([veh.base_lat, veh.base_lng]).bindPopup('База: ' + veh.plate));
        } else if (veh.route_json) {
            const routePoints = JSON.parse(veh.route_json).map(p => [p.lat, p.lng]);
            const routeLine = L.polyline(routePoints, {
                color: '#f59e0b',
                weight: 4,
                dashArray: '6,6'
            }).addTo(routeLayer);
            routeLine.bindTooltip(`Коридор: ${veh.corridor_km} км`, { permanent: true, direction: 'top' });
        }

        data.forEach(c => {
            markersLayer.addLayer(L.marker([c.load_lat, c.load_lng]).bindPopup(`<b>${c.title}</b><br>Погрузка`));
            markersLayer.addLayer(L.marker([c.unload_lat, c.unload_lng]).bindPopup(`<b>${c.title}</b><br>Выгрузка`));
            routeLayer.addLayer(L.polyline([[c.load_lat, c.load_lng], [c.unload_lat, c.unload_lng]], {
                color: '#10b981',
                weight: 2,
                dashArray: '3,5'
            }));
        });

        if (data.length || markersLayer.getLayers().length) {
            try { map.fitBounds(markersLayer.getBounds()); } catch (e) {}
        }
    } else {
        const cargo = getSelectedCargo();
        if (!cargo) return;

        radiusCircle = L.circle([cargo.load_lat, cargo.load_lng], {
            radius: state.radius * 1000,
            color: '#2563eb',
            weight: 1,
            fillOpacity: 0.05
        }).addTo(map);

        markersLayer.addLayer(L.marker([cargo.load_lat, cargo.load_lng]).bindPopup('Погрузка: ' + cargo.load_address));
        markersLayer.addLayer(L.marker([cargo.unload_lat, cargo.unload_lng]).bindPopup('Выгрузка: ' + cargo.unload_address));
        routeLayer.addLayer(L.polyline([[cargo.load_lat, cargo.load_lng], [cargo.unload_lat, cargo.unload_lng]], {
            color: '#10b981',
            weight: 2,
            dashArray: '3,5'
        }));

        data.forEach(v => {
            markersLayer.addLayer(L.marker([v.base_lat, v.base_lng]).bindPopup(`<b>${v.plate}</b> · ${v.body_type}<br>${v.base_address}`));
        });

        if (data.length || markersLayer.getLayers().length) {
            try { map.fitBounds(markersLayer.getBounds()); } catch (e) {}
        }
    }
}

function respond(e) {
    const id = e.currentTarget.dataset.id;
    const body = new URLSearchParams();
    body.set('action', 'respond');

    if (state.role === 'carrier') {
        body.set('entity_type', 'vehicle_to_cargo');
        body.set('vehicle_id', state.vehicleId);
        body.set('cargo_id', id);
        const veh = getSelectedVehicle();
        if (veh) body.set('from_user_id', veh.user_id);
    } else {
        body.set('entity_type', 'cargo_to_vehicle');
        body.set('cargo_id', state.cargoId);
        body.set('vehicle_id', id);
        const cargo = getSelectedCargo();
        if (cargo) body.set('from_user_id', cargo.user_id);
    }

    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
        .then(r => r.json())
        .then(res => {
            alert(res.ok ? 'Отклик отправлен' : 'Ошибка: ' + (res.error || 'неизвестная ошибка'));
            loadData();
        });
}