export interface User {
    id: number;
    name: string;
    email: string;
}

export interface Vinculo {
    id: number;
    user_id: number;
    tenant_id: number;
    papel: 'admin' | 'proprietario' | 'inquilino';
    status: 'ativo' | 'inativo' | 'pendente';
    user: User;
}

export interface ImovelFoto {
    id: number;
    imovel_id: number;
    caminho: string;
    url: string;
    nome_arquivo: string;
    legenda: string | null;
    ordem: number;
    mime_type: string;
    tamanho_bytes: number;
}

export interface DadosBancarios {
    id: number;
    vinculo_id: number;
    apelido: string;
    banco_codigo: string;
    banco_nome: string;
    agencia: string;
    conta: string;
    tipo_conta: 'corrente' | 'poupanca';
    pix_tipo: 'cpf' | 'cnpj' | 'email' | 'telefone' | 'aleatoria' | null;
    pix_chave: string | null;
}

export interface Titularidade {
    id: number;
    imovel_id: number;
    vinculo_id: number;
    dados_bancarios_id: number | null;
    tipo_titular: 'pessoa_fisica' | 'empresa' | 'inventario';
    papel: 'responsavel' | 'observador';
    percentual: string; // decimal vem como string do backend
    vinculo: Vinculo;
    dados_bancarios: DadosBancarios | null;
}

export interface ContratoResponsabilidade {
    id: number;
    contrato_id: number;
    descricao: string;
    responsavel: 'proprietario' | 'inquilino';
    valor: string | null;
    periodicidade: 'mensal' | 'anual' | 'avulso';
    predefinido: boolean;
    observacoes: string | null;
}

export interface Fiador {
    id: number;
    contrato_id: number;
    nome: string;
    cpf: string;
    rg: string | null;
    telefone: string;
    email: string | null;
    profissao: string | null;
    estado_civil: string | null;
    cep: string;
    logradouro: string;
    numero: string;
    complemento: string | null;
    bairro: string;
    cidade: string;
    uf: string;
}

export interface Imovel {
    id: number;
    cep: string;
    logradouro: string;
    numero: string;
    complemento: string | null;
    bairro: string;
    cidade: string;
    uf: string;
    tipo: string;
    status: string;
    quartos: number | null;
    suites: number | null;
    banheiros: number | null;
    vagas_garagem: number | null;
    andar: number | null;
    area_m2: string | null;
    valor_aluguel_sugerido: string | null;
    observacoes: string | null;
    created_at: string;
    updated_at: string;
    foto_principal: ImovelFoto | null;
    fotos: ImovelFoto[];
    titularidades: Titularidade[];
}
