import {MathfieldElement} from 'mod_latex/lib/mathlive';
import Ajax from 'core/ajax';

export default class Latex {

    /**
     * @type {string}
     * @private
     * @description Формула которую будем редактировать
     */
    _formula = '';

    /**
     * @type {string}
     * @private
     * @require
     * @description Контейнер в котором будет отображаться формула для редактирование
     */
    _formulaContainer = '';

    /**
     * @type {number}
     * @private
     * @description Продолжительность мигания кнопки при попытке ручного ввода
     */
    _tooltipDuration = 4000;

    /**
     * @type {boolean}
     * @private
     * @description Запущено ли сейчас мигание кнопки
     */
    _tooltipIsRun = false;

    /**
     * @type {string}
     * @private
     * @description Цвет мигания кнопки
     */
    _tooltipColor = '#6c757d';

    /**
     * @type {number}
     * @private
     * @description Контейнер в который будет помещен интервал
     */
    _tooltipInterval = 0;

    /**
     * @type {boolean}
     * @private
     * @description Корректные ли параметры внутри класса
     */
    _isValid = true;

    /**
     * @type {boolean}
     * @private
     * @description Отображать формулу только для чтения
     */
    _readOnly = false;

    /**
     * @type {boolean}
     * @private
     * @description Разрешить ввод с реальной клавиатуры
     */
    _isRealKeyboard = false;

    /**
     * @type {string}
     * @private
     * @description Иконка для вызова клавиатуры
     */
    _icon = `<svg
        style="width: 21px;"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 512 512">
            <path
                fill="currentColor"
                d="M192 288H32c-18 0-32 14-32 32v160c0 18 14 32 32 32h160c18 0 32-14
                32-32V320c0-18-14-32-32-32zm-29 140c3 3 3 8 0 12l-11 11c-4 3-9 3-12
                0l-28-28-28 28c-3 3-8 3-12 0l-11-11c-3-4-3-9 0-12l28-28-28-28c-3-3-3-8
                0-12l11-11c4-3 9-3 12 0l28 28 28-28c3-3 8-3 12 0l11 11c3 4 3 9 0 12l-28
                28 28 28zM480 0H320c-18 0-32 14-32  32v160c0 18 14 32 32 32h160c18 0
                32-14 32-32V32c0-18-14-32-32-32zm-16 120c0 4-4 8-8 8h-40v40c0 4-4 8-8
                8h-16c-4 0-8-4-8-8v-40h-40c-4 0-8-4-8-8v-16c0-4 4-8 8-8h40V56c0-4 4-8
                8-8h16c4 0 8 4 8 8v40h40c4 0 8 4 8 8v16zm16 168H320c-18 0-32 14-32
                32v160c0 18 14 32 32 32h160c18 0 32-14 32-32V320c0-18-14-32-32-32zm-16
                152c0 4-4 8-8 8H344c-4 0-8-4-8-8v-16c0-4 4-8 8-8h112c4 0 8 4 8 8v16zm0-64c0
                4-4 8-8 8H344c-4 0-8-4-8-8v-16c0-4 4-8 8-8h112c4 0 8 4 8 8v16zM192 0H32C14
                0 0 14 0 32v160c0 18 14 32 32 32h160c18 0 32-14 32-32V32c0-18-14-32-32-32zm-16
                120c0 4-4 8-8 8H56c-4 0-8-4-8-8v-16c0-4 4-8 8-8h112c4 0 8 4 8 8v16z"/>
    </svg>`;

    /**
     * @type {MathfieldElement}
     * @private
     * @description контейнер для хранения объекта для постройки формул
     */
    _mathField;

    /**
     * @type {[]}
     * @private
     * @description массив с файлами для конфигурации клавиатуры
     */
    _configs = [];

    /**
     * @type {[]}
     * @private
     */
    _promises = [];

    get formula() {
        return this._formula;
    }

    set formula(value) {
        this._formula = value;
    }

    get formulaContainer() {
        return this._formulaContainer;
    }

    set formulaContainer(value) {
        this._formulaContainer = value;
    }

    get tooltipDuration() {
        return this._tooltipDuration;
    }

    set tooltipDuration(value) {
        this._tooltipDuration = value;
    }

    get tooltipIsRun() {
        return this._tooltipIsRun;
    }

    set tooltipIsRun(value) {
        this._tooltipIsRun = value;
    }

    get tooltipColor() {
        return this._tooltipColor;
    }

    set tooltipColor(value) {
        this._tooltipColor = value;
    }

    get isValid() {
        return this._isValid;
    }

    set isValid(value) {
        this._isValid = value;
    }

    get readOnly() {
        return this._readOnly;
    }

    set readOnly(value) {
        this._readOnly = value;
    }

    get isRealKeyboard() {
        return this._isRealKeyboard;
    }

    set isRealKeyboard(value) {
        this._isRealKeyboard = value;
    }

    get icon() {
        return this._icon;
    }

    set icon(value) {
        this._icon = value;
    }

    constructor(options) {
        this.formulaContainer = options.formulaContainer;
        this.tooltipDuration = options.tooltipDuration;
        this.formula = options.formula;
        this.readOnly = options.readOnly ?? false;

        this.init();
    }

    /**
     * @returns {null|Latex}
     * @description запускаем работу обертки
     */
    async init() {
        this._mathField = new MathfieldElement();

        this.validParameters();

        if (!this.isValid) {
            return null;
        }
        this._mathField.setOptions(await this.setOptionsMathField());
        this.bindEvents();
        this.appendChild();

        this._mathField.value = this.formula;

        return this;
    }

    /**
     * @description верифицируем обязательные параметры
     */
    validParameters() {
        if (!this.formulaContainer) {
            window.console.error('Не указан обязательный параметр formulaContainer');
            this.isValid = false;
        }
        if (document.querySelectorAll(this.formulaContainer).length > 1) {
            window.console.error('найдено больше одного селектора для formulaContainer');
            this.isValid = false;
        }
    }

    /**
     * @return {Object}
     * @description устанавливаем значения для виджета
     */
    async setOptionsMathField() {

        let options = {
            locale: 'ru',
        };
        if (!this.readOnly) {

            await this.getKeyboardConfiguration();

            let keyboardList = {};
            let customVirtualKeyboardLayers = {};
            let virtualKeyboards = [];

            for (const item of this._configs) {
                keyboardList[item.keyboard] = {
                    "tooltip": item.tooltip,
                    "label": item.label,
                    "layer": item.layer,
                };
                customVirtualKeyboardLayers[item.layer] = item.data;
                virtualKeyboards.push(item.keyboard);
            }
            virtualKeyboards.push('all');
            options = {
                virtualKeyboardMode: "manual",
                virtualKeyboardLayout: 'qwertz',
                virtualKeyboardToggleGlyph: this.icon,
                virtualKeyboardTheme: 'apple',
                customVirtualKeyboardLayers: customVirtualKeyboardLayers,
                customVirtualKeyboards: keyboardList,
                virtualKeyboards: virtualKeyboards.join(' '),
                ...options
            };
        }

        return options;
    }

    /**
     * @description устанавливаем слушатели на события
     */
    bindEvents() {
        this._mathField.addEventListener('keystroke', this.keystrokeEvent);
        this._mathField.addEventListener('change', this.changeEvent);
        this._mathField.addEventListener('virtual-keyboard-toggle', this.virtualKeyboardToggle);
    }

    /**
     * @description получаем скрытый корень
     * @returns {ShadowRoot}
     */
    getShadowRoot() {
        return document.querySelector(this.formulaContainer).parentElement.querySelector('math-field').shadowRoot;
    }

    /**
     * @description получаем селектор обертки кнопки
     * @returns {Element}
     */
    getVirtualKeyboard() {
        return this.getShadowRoot().querySelector('.ML__virtual-keyboard-toggle');
    }

    /**
     * @description обработчик события нажатие на кнопку
     * @param {Object} event
     */
    keystrokeEvent = (event) => {
        if (event.detail.event.type !== 'keypress') {
            if (this.readOnly) {
                event.preventDefault();
                if (!this.tooltipIsRun && !this._tooltipInterval) {
                    let toggle = true;
                    this._tooltipInterval = setInterval(() => {
                        this.getVirtualKeyboard().style.backgroundColor = toggle ? this.tooltipColor : 'transparent';
                        toggle = !toggle;
                        if (!this.tooltipIsRun) {
                            this.tooltipIsRun = true;
                        }
                    }, 400);

                    setTimeout(() => {
                        this.breakInterval();
                    }, this.tooltipDuration);
                }
            }
        }
    };

    /**
     * @description обработчик события изменения формулы
     */
    changeEvent = () => {
        let evt = document.createEvent("HTMLEvents");
        evt.initEvent("change", false, true);
        document.querySelector(this.formulaContainer).value = this._mathField.value;
        document.querySelector(this.formulaContainer).dispatchEvent(evt);
    };

    /**
     * @description обработчик события открытие виртуальной клавиатуры
     */
    virtualKeyboardToggle = () => {
        this.breakInterval();
    };

    /**
     * @description останавливаем моргание кнопки отрытия виртуальной клавиатуры
     */
    breakInterval = () => {
        if (this._tooltipInterval) {
            clearInterval(this._tooltipInterval);
            this._tooltipInterval = 0;
            this.tooltipIsRun = false;
            this.getVirtualKeyboard().style.backgroundColor = 'transparent';
        }
    };

    /**
     * @description устанавливаем созданную формулу в контейнер для отображения
     */
    appendChild() {
        document.querySelector(this.formulaContainer).parentElement.appendChild(this._mathField);
    }

    /**
     * @return {Promise<void>}
     * @description Запрашиваем конфигурацию виртуальных клавиатур
     */
    async getKeyboardConfiguration() {
        let promise = await Ajax.call([{
            methodname: 'keyboard_configurations',
            args: {}
        }]);

        (await Promise.race(promise)).forEach(({keyboard, layer, label, tooltip, data}) => {
            data.rows = JSON.parse(data.rows);
            this._configs.push({keyboard, layer, label, tooltip, data});
        });
    }
}