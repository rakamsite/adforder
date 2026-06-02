/* Filter Inquiry Portal frontend scripts. */
(function () {
	'use strict';

	function getCities() {
		if (window.fipProfileCities && typeof window.fipProfileCities === 'object') {
			return window.fipProfileCities;
		}

		return {};
	}

	function updateCitySelect(provinceSelect, citySelect) {
		var cities = getCities();
		var province = provinceSelect.value;
		var selectedCity = citySelect.getAttribute('data-selected-city') || citySelect.value || '';
		var provinceCities = cities[province] || [];

		citySelect.innerHTML = '';

		var placeholder = document.createElement('option');
		placeholder.value = '';
		placeholder.textContent = province ? 'انتخاب شهر' : 'ابتدا استان را انتخاب کنید';
		citySelect.appendChild(placeholder);

		provinceCities.forEach(function (city) {
			var option = document.createElement('option');
			option.value = city;
			option.textContent = city;

			if (city === selectedCity) {
				option.selected = true;
			}

			citySelect.appendChild(option);
		});

		citySelect.disabled = !province;
		citySelect.setAttribute('data-selected-city', citySelect.value || '');
	}

	function initProfileForm(form) {
		var provinceSelect = form.querySelector('.fip_province_select');
		var citySelect = form.querySelector('.fip_city_select');

		if (!provinceSelect || !citySelect) {
			return;
		}

		updateCitySelect(provinceSelect, citySelect);

		provinceSelect.addEventListener('change', function () {
			citySelect.setAttribute('data-selected-city', '');
			updateCitySelect(provinceSelect, citySelect);
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.fip_profile_form').forEach(initProfileForm);
	});
}());
