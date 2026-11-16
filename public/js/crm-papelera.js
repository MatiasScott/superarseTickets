document.addEventListener("DOMContentLoaded", () => {
  "use strict";

  const tab = document.getElementById("crm-tab-trash");
  const tbody = document.getElementById("crmTrashRows");
  const search = document.getElementById("crmTrashSearch");
  const refresh = document.getElementById("crmTrashRefresh");
  const previous = document.getElementById("crmTrashPrevious");
  const next = document.getElementById("crmTrashNext");
  const pagination = document.getElementById("crmTrashPagination");
  const status = document.getElementById("crmTrashStatus");
  const canRestore =
    document.getElementById("crmTrashPermissions")?.dataset.canRestore === "1";

  if (
    !tab ||
    !tbody ||
    !search ||
    !refresh ||
    !previous ||
    !next ||
    !pagination ||
    !status
  ) {
    return;
  }

  const baseUrl = String(window.BASE_URL || "/").replace(/\/?$/, "/");

  let currentPage = 1;
  let totalPages = 1;
  let loaded = false;
  let requestId = 0;
  let searchTimer = null;

  function setStatus(message, isError = false) {
    status.textContent = message;
    status.classList.toggle("text-danger", isError);
    status.classList.toggle("text-muted", !isError);
  }

  function showMessage(message) {
    tbody.replaceChildren();

    const row = document.createElement("tr");
    const cell = document.createElement("td");

    cell.colSpan = 7;
    cell.className = "text-center text-muted py-4";
    cell.textContent = message;

    row.appendChild(cell);
    tbody.appendChild(row);
  }

  function createCell(value) {
    const cell = document.createElement("td");
    cell.textContent = value == null ? "" : String(value);
    return cell;
  }

  function renderRows(prospectos) {
    tbody.replaceChildren();

    if (!Array.isArray(prospectos) || prospectos.length === 0) {
      showMessage("No se encontraron clientes potenciales eliminados.");
      return;
    }

    const fragment = document.createDocumentFragment();

    prospectos.forEach((prospecto) => {
      const row = document.createElement("tr");

      row.dataset.contactId = String(prospecto.contacto_id || "");

      const nombre = [prospecto.nombre, prospecto.apellido]
        .filter(Boolean)
        .join(" ");

      row.appendChild(createCell(nombre || "Sin nombre"));
      row.appendChild(createCell(prospecto.email || "—"));
      row.appendChild(createCell(prospecto.carrera || "—"));
      row.appendChild(createCell(prospecto.asesor || "—"));
      row.appendChild(createCell(prospecto.etapa_anterior || "—"));
      row.appendChild(createCell(prospecto.deleted_at || "—"));

      const actionsCell = document.createElement("td");
      actionsCell.className = "text-end";

      const restoreButton = document.createElement("button");
      restoreButton.type = "button";
      restoreButton.className =
        "btn btn-sm btn-outline-success crm-trash-restore";
      restoreButton.dataset.contactId = String(prospecto.contacto_id || "");

      const restoreIcon = document.createElement("i");
      restoreIcon.className = "bi bi-arrow-counterclockwise";

      if (canRestore) {
      restoreButton.appendChild(restoreIcon);
      restoreButton.appendChild(document.createTextNode(" Restaurar"));
      }

      actionsCell.appendChild(restoreButton);
      row.appendChild(actionsCell);

      fragment.appendChild(row);
    });

    tbody.appendChild(fragment);
  }

  async function loadTrash(page = 1) {
    const thisRequest = ++requestId;

    setStatus("Consultando papelera...");
    showMessage("Cargando registros...");

    refresh.disabled = true;
    previous.disabled = true;
    next.disabled = true;

    const url = new URL(
      `${baseUrl}crm/prospectos/papelera`,
      window.location.origin,
    );

    url.searchParams.set("page", String(page));
    url.searchParams.set("q", search.value.trim());

    try {
      const response = await fetch(url.toString(), {
        method: "GET",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
        },
      });

      const data = await response.json();

      if (thisRequest !== requestId) return;

      if (!response.ok || !data.success) {
        throw new Error(
          data.error || data.message || "No se pudo consultar la papelera.",
        );
      }

      currentPage = Math.max(1, Number(data.page) || 1);
      totalPages = Math.max(1, Number(data.pages) || 1);

      renderRows(data.prospectos);

      pagination.textContent = `Página ${currentPage} de ${totalPages}`;

      setStatus(`${Number(data.total) || 0} clientes potenciales eliminados`);

      previous.disabled = currentPage <= 1;
      next.disabled = currentPage >= totalPages;
      loaded = true;
    } catch (error) {
      if (thisRequest !== requestId) return;

      console.error("[CRM PAPELERA]", error);
      showMessage("No fue posible cargar la papelera.");
      setStatus(error.message || "Error de consulta", true);

      loaded = false;
    } finally {
      if (thisRequest === requestId) {
        refresh.disabled = false;
      }
    }
  }

  tab.addEventListener("shown.bs.tab", () => {
    if (!loaded) {
      loadTrash(1);
    }
  });

  refresh.addEventListener("click", () => {
    loadTrash(currentPage);
  });

  search.addEventListener("input", () => {
    clearTimeout(searchTimer);

    searchTimer = setTimeout(() => {
      loadTrash(1);
    }, 350);
  });

  previous.addEventListener("click", () => {
    if (currentPage > 1) {
      loadTrash(currentPage - 1);
    }
  });

  next.addEventListener("click", () => {
    if (currentPage < totalPages) {
      loadTrash(currentPage + 1);
    }
  });

  if (tab.classList.contains("active")) {
    loadTrash(1);
  }

  tbody.addEventListener("click", async (event) => {
    const button = event.target.closest(".crm-trash-restore");

    if (!button || button.disabled) {
      return;
    }

    const contactId = Number(button.dataset.contactId || 0);

    if (!Number.isSafeInteger(contactId) || contactId <= 0) {
      setStatus("Identificador de contacto inválido.", true);
      return;
    }

    const row = button.closest("tr");
    const prospectName =
      row?.cells[0]?.textContent?.trim() || "este cliente potencial";

    const confirmed = window.confirm(
      `¿Deseas restaurar a "${prospectName}"?\n\n` +
        "El cliente potencial volverá a la etapa 1 Etapa interesado. " +
        "Se conservarán sus datos y su historial.",
    );

    if (!confirmed) {
      return;
    }

    button.disabled = true;
    setStatus("Restaurando cliente potencial...");

    try {
      const response = await fetch(`${baseUrl}crm/restoreProspect`, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
          Accept: "application/json",
        },
        body: new URLSearchParams({
          contacto_id: String(contactId),
        }),
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(
          data.error ||
            data.message ||
            "No se pudo restaurar el cliente potencial.",
        );
      }

      window.showGlobalNotification?.(
        "Cliente potencial restaurado correctamente.",
        "success",
      );

      // Actualizar el listado de la papelera.
      await loadTrash(currentPage);
    } catch (error) {
      console.error("[CRM RESTAURAR]", error);

      setStatus(
        error.message || "No se pudo restaurar el cliente potencial.",
        true,
      );

      window.showGlobalNotification?.(
        error.message || "Error al restaurar.",
        "error",
      );
    } finally {
      button.disabled = false;
    }
  });
});
