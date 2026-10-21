import { Building, Building2, Landmark, UserRound } from 'lucide-vue-next';

/**
 * Los catálogos, por el slug de su URL (CatalogController::CATALOGS). Los
 * iconos son los mismos que llevan esos campos en los formularios.
 */
export const CATALOGS = {
    'unidades-de-negocio': {
        title: 'Unidades de negocio',
        singular: 'unidad de negocio',
        create: 'Nueva unidad de negocio',
        icon: Building2,
        placeholder: 'Ej. Constructora',
        uppercase: false,
    },
    aseguradoras: {
        title: 'Aseguradoras',
        singular: 'aseguradora',
        create: 'Nueva aseguradora',
        icon: Landmark,
        placeholder: 'Ej. ZURICH',
        uppercase: true,
    },
    afianzadoras: {
        title: 'Afianzadoras',
        singular: 'afianzadora',
        create: 'Nueva afianzadora',
        icon: Building,
        placeholder: 'Ej. FIANZAS MONTERREY',
        uppercase: true,
    },
    beneficiarios: {
        title: 'Beneficiarios',
        singular: 'beneficiario',
        create: 'Nuevo beneficiario',
        icon: UserRound,
        placeholder: 'Ej. SECRETARÍA DE OBRAS PÚBLICAS',
        uppercase: true,
    },
};

/** [{ id, name }] del catálogo → opciones de FormSelect. */
export const catalogOptions = (items = []) => items.map(({ id, name }) => ({ value: id, label: name }));
