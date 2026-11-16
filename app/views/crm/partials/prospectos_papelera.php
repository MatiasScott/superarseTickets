<div
    class="tab-pane fade"
    id="crm-pane-trash"
    role="tabpanel"
    aria-labelledby="crm-tab-trash"
    tabindex="0">

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">
                <i class="bi bi-trash3"></i>
                Papelera de clientes potenciales
            </h2>
        </div>

        <div class="card-body">
            <p class="text-muted small">
                Consulta los clientes potenciales eliminados.
                Sus registros se conservan para una posible restauración.
            </p>

            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-6">
                    <label for="crmTrashSearch" class="form-label">
                        Buscar cliente potencial
                    </label>
                    <input
                        type="search"
                        class="form-control"
                        id="crmTrashSearch"
                        placeholder="Nombre, correo o identificación">
                </div>

                <div class="col-md-auto">
                    <button
                        type="button"
                        class="btn btn-outline-primary"
                        id="crmTrashRefresh">
                        <i class="bi bi-arrow-clockwise"></i>
                        Actualizar
                    </button>
                </div>
            </div>

            <div
                id="crmTrashStatus"
                class="small text-muted mb-3"
                role="status"
                aria-live="polite">
                Selecciona Papelera para consultar los registros.
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Cliente potencial</th>
                            <th>Correo</th>
                            <th>Carrera</th>
                            <th>Asesor</th>
                            <th>Etapa anterior</th>
                            <th>Fecha de eliminación</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>

                    <tbody id="crmTrashRows">
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Sin consultar
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center gap-2">
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    id="crmTrashPrevious"
                    disabled>
                    Anterior
                </button>

                <span id="crmTrashPagination" class="small text-muted">
                    Página 1 de 1
                </span>

                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    id="crmTrashNext"
                    disabled>
                    Siguiente
                </button>
            </div>
        </div>
    </div>
</div>