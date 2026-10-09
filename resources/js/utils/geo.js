// Geolocalização do cliente: usada só com finalidade ativa (sugerir endereço,
// ou avisar que o cliente está longe do endereço de entrega escolhido no pedido).
// Nunca roda em background nem é usada pra pontuar/rastrear o cliente.

export function getCurrentPosition(options = {}) {
    return new Promise((resolve, reject) => {
        if (!('geolocation' in navigator)) {
            reject(new Error('geolocation_unavailable'));
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => resolve({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
            }),
            (error) => reject(error),
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000, ...options }
        );
    });
}

export function haversineKm(lat1, lng1, lat2, lng2) {
    const toRad = (value) => (value * Math.PI) / 180;
    const earthRadiusKm = 6371;

    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);

    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) * Math.sin(dLng / 2);

    return earthRadiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export async function reverseGeocode(latitude, longitude) {
    const res = await fetch(
        `https://nominatim.openstreetmap.org/reverse?lat=${latitude}&lon=${longitude}&format=json`,
        { headers: { 'Accept-Language': 'pt-BR' } }
    );
    const data = await res.json();
    const addr = data.address ?? {};

    return {
        label: data.display_name ?? null,
        street: addr.road ?? addr.pedestrian ?? null,
        number: addr.house_number ?? null,
        neighborhood: addr.suburb ?? addr.neighbourhood ?? null,
        city: addr.city ?? addr.town ?? addr.village ?? null,
        state: addr.state_code ?? (addr.state ? addr.state.slice(0, 2).toUpperCase() : null),
        zip_code: addr.postcode ?? null,
    };
}
