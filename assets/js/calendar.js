document.addEventListener('DOMContentLoaded', function () {
    const visitTypeInput = document.getElementById('lloji_vizites');
    const durationInput = document.getElementById('kohezgjatja_min');

    if (visitTypeInput && durationInput) {
        const durationsByType = {
            'Konsultë e shpejtë': 15,
            'Kontrollë e përgjithshme': 30,
            'Kontrollë kardiologjike': 45,
            'Ekografi': 30,
            'Kontrollë pediatrike': 30,
            'Ndërhyrje ambulatore': 60
        };
        visitTypeInput.addEventListener('change', function () {
            const matched = durationsByType[visitTypeInput.value];
            if (matched) {
                durationInput.value = String(matched);
            }
        });
    }
});
