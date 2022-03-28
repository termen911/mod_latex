import Latex from "./module/Latex";
import {initAction, showActions} from "./module/answerAction";

export const initLatex = (formulaContainer, formula, readOnly, tooltipDuration) => {
    new Latex({
        formulaContainer: formulaContainer,
        tooltipDuration: tooltipDuration,
        readOnly: readOnly,
        formula: formula
    });
};

export const actionsOnDates = () => {
    let event = new Event('change');
    document.getElementById('id_date_start_enabled').checked = true;
    document.getElementById('id_date_start_enabled').dispatchEvent(event);
    document.getElementById('id_date_end_enabled').checked = true;
    document.getElementById('id_date_end_enabled').dispatchEvent(event);
};

export const initSteps = (saveInfo = '') => {
    initAction(saveInfo);
};
export const initShowActions = () => {
    showActions();
    showActionsNew();
};

export const initUpdate = () => {

    let isUpdate = false;
    let isUpdateInput = document.querySelectorAll('[name="is_update"]');
    if (isUpdateInput.length) {
        isUpdate = Boolean(isUpdateInput[0].value);
    }

    if (isUpdate) {
        getSubmitButton().forEach((item) => {
            item.addEventListener('click', (event) => {
                if (!confirm('Если вы обновите задание все оценки будут пересчитаны заново! Выполнить?')) {
                    event.preventDefault();
                }
            });
        });
    }

};

const getSubmitButton = () => {
    let submitbutton = document.querySelectorAll('[name="submitbutton"]');
    let submitbutton2 = document.querySelectorAll('[name="submitbutton2"]');
    return [submitbutton[0], submitbutton2[0]];
};


const showActionsNew = () => {
    let items = document.getElementsByClassName('latex_formula_render');

    for (let item of items) {
        let latexFormula = item.dataset.latexFormula;
        let latexReadonly = item.dataset.latexReadonly;

        let dataObject;

        try {
            dataObject = JSON.parse(latexFormula);
        } catch (e) {
            dataObject = latexFormula;
        }

        if (typeof dataObject === "object") {
            for (let value in dataObject) {
                let id = `${value}-${Date.now()}`;
                item.append(createContainerNew(id));
                initLatex(`#${id}`, dataObject[value], latexReadonly);
            }
        } else {
            let id = `new-id-latex-${Date.now()}`;
            item.append(createContainerNew(id));
            initLatex(`#${id}`, dataObject, latexReadonly);
        }


    }
};

const createContainerNew = (id) => {
    let input = document.createElement('input');
    input.id = id;
    input.classList.add('d-none');
    return input;
};

