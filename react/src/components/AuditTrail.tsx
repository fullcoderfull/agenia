import type { CSSProperties } from 'react';
import type { Dictionary } from '@/i18n/dictionaries';
import { Icon } from './Icon';

type T = Dictionary['trail'];
type Plain = { title: string; detail: string; meta: string };

// Piste d'audit d'exemple. Rejouée une seule fois au chargement (CSS),
// état final affiché directement si l'utilisateur limite les animations.
// L'étape de consentement montre « en attente » puis la décision de la personne.

function stepStyle(i: number): CSSProperties {
  return { ['--i' as string]: i } as CSSProperties;
}

function PlainStep({ i, step }: { i: number; step: Plain }) {
  return (
    <li className="step" style={stepStyle(i)}>
      <span className="step-dot">
        <Icon name="check" size={12} />
      </span>
      <span>
        <span className="step-title">{step.title}</span>
        <span className="step-detail">{step.detail}</span>
      </span>
      <span className="step-meta">{step.meta}</span>
    </li>
  );
}

export function AuditTrail({ t }: { t: T }) {
  const c = t.consent;
  return (
    <figure className="trail" aria-labelledby="trail-title">
      <div className="trail-head">
        <h2 id="trail-title">{t.title}</h2>
        <span className="trail-tag">{t.tag}</span>
      </div>
      <p className="trail-ask">
        {t.asker} <span className="trail-q">{t.question}</span>
      </p>
      <ol className="trail-steps">
        <PlainStep i={0} step={t.rights} />
        <PlainStep i={1} step={t.model} />

        <li className="step" style={stepStyle(2)}>
          <span className="swap">
            <span className="step-dot is-before" aria-hidden="true">
              !
            </span>
            <span className="step-dot is-after">
              <Icon name="check" size={12} />
            </span>
          </span>
          <span>
            <span className="swap">
              <span className="step-title is-before" aria-hidden="true">
                {c.titlePending}
              </span>
              <span className="step-title is-after">{c.titleDone}</span>
            </span>
            <span className="step-detail">{c.detail}</span>
            <span className="swap">
              <span className="consent-actions is-before" aria-hidden="true">
                <span className="yes">{c.allow}</span>
                <span className="no">{c.deny}</span>
              </span>
              <span className="consent-by is-after">{c.by}</span>
            </span>
          </span>
          <span className="swap step-meta">
            <span className="is-before" aria-hidden="true">
              {c.metaPending}
            </span>
            <span className="is-after">{c.metaDone}</span>
          </span>
        </li>

        <PlainStep i={3} step={t.log} />
      </ol>
      <div className="trail-foot">
        <span>
          <b>{t.footDurationValue}</b>
          {t.footDuration}
        </span>
        <span>
          <b>{t.footCostValue}</b>
          {t.footCost}
        </span>
        <span>
          <b>{t.footHumanValue}</b>
          {t.footHuman}
        </span>
      </div>
    </figure>
  );
}
