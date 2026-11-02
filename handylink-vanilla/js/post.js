document.addEventListener("DOMContentLoaded", function () {
  var form = document.getElementById("postForm");
  if (!form) return;
  var modeButtons = Array.from(document.querySelectorAll(".mode-btn"));
  var directList = document.getElementById("directProviderList");
  var flowInput = document.getElementById("bookingFlow");
  var scheduleInput = document.getElementById("scheduleType");
  var scheduleButtons = Array.from(document.querySelectorAll(".urgency-chip"));
  var dateWrap = document.getElementById("scheduleDateWrap");
  var timeWrap = document.getElementById("scheduleTimeWrap");
  var dateInput = document.getElementById("scheduledDate");
  var timeInput = document.getElementById("preferredTime");
  var timeRequiredMark = document.getElementById("timeRequiredMark");
  var timeHint = document.getElementById("timeHint");
  var providerRadios = Array.from(document.querySelectorAll('input[name="direct_provider_id"]'));
  var locationButton = document.getElementById("useCurrentLocation");
  var locationInput = document.getElementById("locationAddress");
  var locationStatus = document.getElementById("locationStatus");

  function setMode(mode) {
    var direct = mode === "direct";
    modeButtons.forEach(function (button) {
      var active = button.getAttribute("data-mode") === mode;
      button.classList.toggle("active", active);
      button.setAttribute("aria-pressed", active ? "true" : "false");
    });
    flowInput.value = direct ? "direct_hire" : "job_post";
    directList.classList.toggle("hidden", !direct);
    providerRadios.forEach(function (radio) {
      radio.required = direct;
      radio.setCustomValidity("");
    });
  }
  function setSchedule(schedule) {
    scheduleInput.value = schedule;
    scheduleButtons.forEach(function (button) {
      var active = button.getAttribute("data-schedule") === schedule;
      button.classList.toggle("active", active);
      button.setAttribute("aria-pressed", active ? "true" : "false");
    });
    var needsDate = schedule === "this_week";
    var needsTime = schedule === "today";
    dateWrap.style.display = needsDate ? "block" : "none";
    timeWrap.style.display = needsDate || needsTime ? "block" : "none";
    dateInput.required = needsDate;
    timeInput.required = needsTime;
    timeRequiredMark.style.display = needsTime ? "inline" : "none";
    timeHint.textContent = needsTime ? "Choose the time you would like the job done." : "Choose a time if you have one in mind.";
  }
  modeButtons.forEach(function (button) {
    button.addEventListener("click", function () { setMode(button.getAttribute("data-mode")); });
  });
  scheduleButtons.forEach(function (button) {
    button.addEventListener("click", function () { setSchedule(button.getAttribute("data-schedule")); });
  });
  providerRadios.forEach(function (radio) {
    radio.addEventListener("change", function () {
      providerRadios.forEach(function (item) {
        item.setCustomValidity("");
        item.closest(".provider-option").classList.toggle("checked", item.checked);
      });
    });
  });
  setMode(flowInput.value === "direct_hire" ? "direct" : "open");
  setSchedule(scheduleInput.value || "flexible");
  providerRadios.forEach(function (radio) {
    radio.closest(".provider-option").classList.toggle("checked", radio.checked);
  });

  if (locationButton && locationInput && locationStatus) {
    locationButton.addEventListener("click", function () {
      if (!navigator.geolocation) {
        locationStatus.textContent = "Location detection is not supported. Enter your broad area manually.";
        return;
      }
      locationButton.disabled = true;
      locationStatus.textContent = "Finding a nearby areaâ€¦";
      navigator.geolocation.getCurrentPosition(function (position) {
        var url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&zoom=14&lat=" +
          encodeURIComponent(position.coords.latitude) + "&lon=" + encodeURIComponent(position.coords.longitude);
        fetch(url, { headers: { Accept: "application/json" } })
          .then(function (response) {
            if (!response.ok) throw new Error("Lookup failed");
            return response.json();
          })
          .then(function (result) {
            var address = result.address || {};
            var neighborhood = address.neighbourhood || address.suburb || address.quarter || address.city_district || address.village || address.town || address.city;
            var city = address.city || address.town || address.municipality || address.state_district || address.county || address.state;
            var parts = [neighborhood, city].filter(function (part, index, all) { return part && all.indexOf(part) === index; });
            if (!parts.length) throw new Error("No broad area");
            locationInput.value = parts.join(", ");
            locationStatus.textContent = "Suggested area: " + locationInput.value + ". Review it before posting. Only the area text is saved.";
          })
          .catch(function () {
            locationStatus.textContent = "We couldn't identify a broad area. Enter it manually; exact coordinates are not saved with the request.";
          })
          .finally(function () { locationButton.disabled = false; });
      }, function () {
        locationStatus.textContent = "Location access was unavailable. Enter your broad area manually.";
        locationButton.disabled = false;
      }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 });
    });
  }
  form.addEventListener("submit", function () {
    if (flowInput.value === "direct_hire" && !providerRadios.some(function (radio) { return radio.checked; }) && providerRadios[0]) {
      providerRadios[0].setCustomValidity("Choose a helper for a direct request.");
    }
    var submitButton = form.querySelector('button[type="submit"]');
    if (submitButton && form.checkValidity()) {
      submitButton.disabled = true;
      submitButton.textContent = "Saving requestâ€¦";
    }
  });
});