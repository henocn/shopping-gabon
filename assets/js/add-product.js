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

function createFieldGroup(labelText, control) {
    const group = document.createElement('div');
    group.className = 'mb-3';

    const label = document.createElement('label');
    label.className = 'form-label';
    label.textContent = labelText;

    group.append(label, control);
    return group;
}

function createRemoveButton(container) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-danger';

    const icon = document.createElement('i');
    icon.className = 'bx bx-trash';
    button.append(icon, document.createTextNode(' Supprimer'));
    button.addEventListener('click', function() {
        container.remove();
    });

    return button;
}

function addCharacteristic() {
    const characteristicDiv = document.createElement('div');
    characteristicDiv.className = 'characteristic-item';

    const title = document.createElement('input');
    title.type = 'text';
    title.className = 'form-control';
    title.name = 'characteristic_title[]';
    title.required = true;

    const image = document.createElement('input');
    image.type = 'file';
    image.className = 'form-control';
    image.name = 'characteristic_image[]';

    const description = document.createElement('textarea');
    description.className = 'form-control';
    description.name = 'characteristic_description[]';
    description.rows = 3;

    characteristicDiv.append(
        createFieldGroup('Titre', title),
        createFieldGroup('Image', image),
        createFieldGroup('Description', description),
        createRemoveButton(characteristicDiv),
    );
    document.getElementById('characteristicsList').appendChild(characteristicDiv);
}

function addVideo() {
    const videoDiv = document.createElement('div');
    videoDiv.className = 'characteristic-item';

    const video = document.createElement('input');
    video.type = 'file';
    video.className = 'form-control';
    video.name = 'video[]';
    video.accept = 'video/*';
    video.required = true;

    const text = document.createElement('textarea');
    text.className = 'form-control';
    text.name = 'video_text[]';
    text.rows = 3;

    videoDiv.append(
        createFieldGroup('Vidéo', video),
        createFieldGroup('Texte', text),
        createRemoveButton(videoDiv),
    );
    document.getElementById('videosList').appendChild(videoDiv);
}


function addPack(){
    const packDiv = document.createElement('div');
    packDiv.className = 'characteristic-item';

    const name = document.createElement('input');
    name.type = 'text';
    name.className = 'form-control';
    name.name = 'pack_name[]';
    name.required = true;

    const image = document.createElement('input');
    image.type = 'file';
    image.className = 'form-control';
    image.name = 'pack_image[]';
    image.required = true;

    const quantity = document.createElement('input');
    quantity.type = 'number';
    quantity.className = 'form-control';
    quantity.name = 'pack_quantity[]';
    quantity.min = '0';
    quantity.required = true;

    const price = document.createElement('input');
    price.type = 'number';
    price.className = 'form-control';
    price.name = 'pack_price[]';
    price.min = '0';
    price.required = true;

    packDiv.append(
        createFieldGroup('Nom du Pack', name),
        createFieldGroup('Image', image),
        createFieldGroup('Quantité', quantity),
        createFieldGroup('Prix du pack', price),
        createRemoveButton(packDiv),
    );
    document.getElementById('packsList').appendChild(packDiv);
}



