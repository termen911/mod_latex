import Templates from 'core/templates';
import Latex from "./Latex";

let isInitEventAddStep = false;
let template = "mod_latex/components/step-answer";
let currentId = '';

export const initAction = (saveInfo) => {
    initEventAddStep();

    let params = [];

    if (!saveInfo) {
        currentId = `step-${getNumberStep()}`;
        params.push({
            idLatex: currentId,
            stepNumber: getNumberStep(),
            formula: ''
        });
    } else {
        let objectStep = JSON.parse(saveInfo);
        let localStep = 1;
        for (let key in objectStep) {
            currentId = key;
            params.push({
                idLatex: currentId,
                stepNumber: localStep,
                formula: objectStep[key]
            });
            localStep++;
        }
    }

    renderStep(params).then(() => {
        for (let item of params) {
            initLatex(`#${item.idLatex}`, item.formula);
            initEventChangeLatexStep(item.idLatex);
        }
        return null;
    });
};

const getNumberStep = () => {
    return document.getElementById('container_steps').children.length + 1;
};

const initEventAddStep = () => {
    if (!isInitEventAddStep) {
        isInitEventAddStep = true;
        document.getElementById('add-step').addEventListener('click', (event) => {
            event.preventDefault();
            initAction();
        });
    }
};

const initEventChangeLatexStep = (id) => {
    let textStepInfo = {};
    document.getElementById(id).addEventListener('change', () => {
        let listSteps = document.getElementById('container_steps').getElementsByTagName('input');
        for (let step of listSteps) {
            if (step.value !== '') {
                textStepInfo[step.id] = step.value;
            }
        }
        getStepInfoInput().value = JSON.stringify(textStepInfo);
    });
};

const getStepInfoInput = () => {
    let textStepContainer = document.querySelectorAll('[name="answeraction"]');
    return textStepContainer[0];
};

const renderStep = (params) => {
    return Templates.render(template, {params})
        .then((html, js) => {
            Templates.appendNodeContents(document.getElementById('container_steps'), html, js);
        })
        .fail(error => {
            window.console.error(error);
        });
};

const initLatex = (formulaContainer, formula, readOnly) => {
    new Latex({formulaContainer, formula, readOnly});
};

/**
 * @description Отображаем информация для хода решения
 */
export const showActions = () => {
    let container = document.getElementById('answer-action-container');
    if (container) {
        let dataString = container.dataset.answerAction;
        let dataObject = JSON.parse(dataString);
        for (let item in dataObject) {
            container.append(createContainer(item));
            initLatex(`#${item}-submissions`, dataObject[item], true);
        }
    }
};

const createContainer = (id) => {
    let input = document.createElement('input');
    input.id = `${id}-submissions`;
    input.classList.add('d-none');
    return input;
};