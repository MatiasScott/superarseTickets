<?php

class Convenio extends Model
{
    protected string $table = 'convenios';

    public function setConnection(PDO $connection): void
    {
        $this->db = $connection;
    }

    public function getColumns(): array
    {
        $stmt = $this->db->query("SHOW COLUMNS FROM convenios");
        $rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
        $columns = [];
        foreach ($rows as $row) {
            $field = (string) ($row['Field'] ?? '');
            if ($field !== '') {
                $columns[] = $field;
            }
        }
        return $columns;
    }

    public function listWithStats(): array
    {
        $pk = $this->resolvePrimaryKeyColumn();
        $sql = "SELECT c.*, c.{$pk} AS id FROM convenios c ORDER BY c.{$pk} DESC";

        $stmt = $this->db->query($sql);
        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

    public function findById(int $id): ?array
    {
        $pk = $this->resolvePrimaryKeyColumn();
        $sql = "SELECT c.*, c.{$pk} AS id FROM convenios c WHERE c.{$pk} = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createConvenio(array $input, int $userId): int
    {
        $columns = $this->getColumns();
        $payload = $this->buildPayload($input, $columns);

        if (in_array('created_by', $columns, true)) {
            $payload['created_by'] = $userId;
        }

        return $this->create($payload);
    }

    public function updateConvenio(int $id, array $input): bool
    {
        $columns = $this->getColumns();
        $payload = $this->buildPayload($input, $columns);

        if (empty($payload)) {
            return true;
        }

        $pk = $this->resolvePrimaryKeyColumn();
        $setParts = [];
        foreach (array_keys($payload) as $column) {
            $setParts[] = "{$column} = :{$column}";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE {$pk} = :_id";
        $stmt = $this->db->prepare($sql);
        $payload['_id'] = $id;
        return $stmt->execute($payload);
    }

    private function resolvePrimaryKeyColumn(): string
    {
        $columns = $this->getColumns();
        return $this->resolveColumn($columns, ['id_convenio', 'id', 'convenio_id']) ?? 'id';
    }

    private function buildPayload(array $input, array $columns): array
    {
        $map = [
            'nombre_empresa' => ['nombre_empresa', 'empresa_institucion', 'empresa', 'institucion'],
            'fecha_inicio' => ['fecha_inicio'],
            'fecha_fin' => ['fecha_fin'],
            'estado_convenio' => ['estado_convenio'],
            'tipo_convenio_acuerdo' => ['tipo_convenio_acuerdo'],
            'tipo_institucion' => ['tipo_institucion'],
            'en_ejecucion' => ['en_ejecucion'],
            'estado' => ['estado'],
            'tipo_convenio' => ['tipo_convenio', 'tipo'],
            'carrera' => ['carrera'],
            'localizacion' => ['localizacion'],
            'ciudad' => ['ciudad'],
            'observaciones' => ['observaciones', 'observacion'],
        ];

        $payload = [];
        foreach ($map as $source => $targets) {
            $column = $this->resolveColumn($columns, $targets);
            if ($column === null) {
                continue;
            }

            if (!array_key_exists($source, $input)) {
                continue;
            }

            $payload[$column] = is_string($input[$source]) ? trim($input[$source]) : $input[$source];
        }

        return $payload;
    }

    public function resolveFirstMatchingColumn(array $candidates): ?string
    {
        $columns = $this->getColumns();
        return $this->resolveColumn($columns, $candidates);
    }

    private function resolveColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }
        return null;
    }
}
