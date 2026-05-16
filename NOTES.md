# Notas Técnicas - Exportação de Alunos

## Instruções de Uso

1. Acesse o painel Filament em `http://localhost/admin`.
2. Faça login com as credenciais padrão (`admin@admin.com` / `admin`).
3. Navegue até o menu **Alunos**.
4. Clique no botão verde **Exportar Alunos** no topo da tabela.
5. O sistema processará a exportação em segundo plano. Uma notificação toast informará que a exportação foi iniciada.
6. Ao concluir, uma notificação no sino (canto superior direito) será disparada com o link de download da planilha.
7. O link de download é uma URL assinada válida por **24 horas**.

---

## Decisões Técnicas

### Performance

- **Buffer de escrita**: As linhas são acumuladas em lotes de 1000 antes de serem gravadas no arquivo via `addRows()`, reduzindo drasticamente as operações de I/O em disco em comparação com chamadas individuais de `addRow()`.
- **OpenSpout em vez de Maatwebsite/Excel**: PhpSpreadsheet (usado pelo maatwebsite/excel) carrega o arquivo inteiro na memória antes de gravar, causando estouro com 200k registros. OpenSpout foi escolhido por implementar streaming nativo, escrevendo linhas diretamente no disco sem acumular em memória, tornando-o adequado para exportações de alto volume.
- **Cursor Eloquent (`cursor()`)**: A consulta utiliza `cursor()` para iterar os ~200 mil registros um a um, mantendo o uso de memória constante. Com `get()`, o Laravel aguardaria o banco retornar todos os 200k registros e os carregaria inteiros na memória antes de processar qualquer um, causando estouro de memória.
- **Subqueries no `addSelect`**: Dados relacionados (documentos, endereços, matrículas, escola mais recente) são carregados via subqueries diretamente no SQL, eliminando o problema de N+1 queries.
- **Job na Fila**: A exportação roda em uma fila (`ExportStudentsJob`), liberando a requisição HTTP imediatamente e evitando timeout no navegador.
- **Sem Problema de Concorrência**: O arquivo é salvo no servidor com timestamp e ID único no nome (`alunos_{timestamp}_{uniqid}.xlsx`), evitando colisões entre exportações simultâneas. O usuário baixa o arquivo sempre como `alunos.xlsx`, independente do nome interno.
- **Cache Distribuído (Redis)**: Em produção, Redis sincronizaria o status da exportação entre múltiplos containers, evitando race conditions e garantindo consistência do estado compartilhado.

### UX (Experiência do Usuário)

- **Notificações no Banco de Dados**: Ao final da exportação, o usuário recebe uma notificação persistente no sino de notificações do Filament. Isso permite que ele feche a página e volte mais tarde para baixar o arquivo.
- **URL Assinada**: O download utiliza `temporarySignedRoute`, garantindo segurança sem exigir autenticação adicional no link.
- **Proteção de Path Traversal**: A rota de download valida o parâmetro `path`, rejeitando qualquer tentativa de acesso a arquivos fora da pasta `exports/`.
- **Writer seguro**: O `Writer` do OpenSpout é encapsulado em um bloco `try/finally`, garantindo que o arquivo seja fechado corretamente mesmo em caso de erro.
- **Renovação do lock**: O cache de controle do botão é renovado a cada 30 segundos durante o processamento, evitando que o botão seja liberado prematuramente em exportações longas.

### Qualidade do Código

- **Extração de Formatadores**: A formatação de CPF, RG e CEP foi extraída para a classe `App\Support\DocumentFormatter`, promovendo reutilização e testabilidade.
- **Separação de Responsabilidades**: O job cuida apenas da geração do arquivo e da notificação; a rota cuida apenas do download; o resource cuida apenas do disparo.
- **Padronização Laravel/Filament**: Uso de Notifications nativas do Filament, rotas web com middleware de autenticação e organização de código seguindo as convenções do framework.
