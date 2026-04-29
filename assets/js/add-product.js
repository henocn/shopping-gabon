function validateForm() {
    const requiredFields = document.querySelectorAll('[required]');
    let isValid = true;
    let errorMessages = [];

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            const label = field.closest('.mb-3').querySelector('.form-label').textContent.trim();
            errorMessages.push(`Le champ "${label}" est obligatoire`);
        }
    });

    if (!isValid) {
        alert(errorMessages.join('\n'));
    }

    return isValid;
}

function toggleSection(section) {
    const sectionElement = document.getElementById(`${section}Section`);
    const button = document.querySelector(`[onclick="toggleSection('${section}')"]`);
    const isHidden = window.getComputedStyle(sectionElement).display === 'none';
    
    if (isHidden) {
        document.querySelectorAll('[id$="Section"]').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.floating-btn').forEach(btn => btn.classList.remove('active'));
        sectionElement.style.display = 'block';
        button.classList.add('active');
    } else {
        sectionElement.style.display = 'none';
        button.classList.remove('active');
    }
}

function addCharacteristic() {
    const characteristicDiv = document.createElement('div');
    characteristicDiv.className = 'characteristic-item';
    characteristicDiv.innerHTML = `
        <div class="mb-3">
            <label class="form-label">Titre</label>
            <input type="text" class="form-control" name="characteristic_title[]" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Image</label>
            <input type="file" class="form-control" name="characteristic_image[]">
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="characteristic_description[]" rows="3"></textarea>
        </div>
        <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()">
            <i class='bx bx-trash'></i> Supprimer
        </button>
    `;
    document.getElementById('characteristicsList').appendChild(characteristicDiv);
}

function addVideo() {
    const videoDiv = document.createElement('div');
    videoDiv.className = 'characteristic-item';
    videoDiv.innerHTML = `
        <div class="mb-3">
            <label class="form-label">Vidéo</label>
            <input type="file" class="form-control" name="video[]" accept="video/*" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Texte</label>
            <textarea class="form-control" name="video_text[]" rows="3"></textarea>
        </div>
        <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()">
            <i class='bx bx-trash'></i> Supprimer
        </button>
    `;
    document.getElementById('videosList').appendChild(videoDiv);
}


function addPack(){
    const packDiv = document.createElement('div');
    packDiv.className = 'characteristic-item';
    packDiv.innerHTML = `
        <div class="mb-3">
            <label class="form-label">Nom du Pack</label>
            <input type="text" class="form-control" name="pack_name[]" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Image</label>
            <input type="file" class="form-control" name="pack_image[]" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Quantité</label>
            <input type="number" class="form-control" name="pack_quantity[]" min="0" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Prix du pack</label>
            <input type="number" class="form-control" name="pack_price[]" min="0" required>
        </div>
        <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()">
            <i class='bx bx-trash'></i> Supprimer
        </button>
    `;
    document.getElementById('packsList').appendChild(packDiv);
}




