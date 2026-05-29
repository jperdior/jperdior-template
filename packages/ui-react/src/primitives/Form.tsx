'use client';

import * as React from 'react';
import {
  Controller,
  type ControllerProps,
  type FieldPath,
  type FieldValues,
  FormProvider,
  useFormContext,
} from 'react-hook-form';
import * as LabelPrimitive from '@radix-ui/react-label';
import { Slot } from '@radix-ui/react-slot';
import { cn } from '../utils/cn';
import { Label } from './Label';

const Form = FormProvider;

type FormFieldContextValue<
  TFieldValues extends FieldValues = FieldValues,
  TName extends FieldPath<TFieldValues> = FieldPath<TFieldValues>,
> = { name: TName };

const FormFieldContext = React.createContext<FormFieldContextValue | null>(null);

function FormField<
  TFieldValues extends FieldValues = FieldValues,
  TName extends FieldPath<TFieldValues> = FieldPath<TFieldValues>,
>(props: ControllerProps<TFieldValues, TName>) {
  return (
    <FormFieldContext.Provider value={{ name: props.name }}>
      <Controller {...props} />
    </FormFieldContext.Provider>
  );
}

function useFormField() {
  const fieldContext = React.useContext(FormFieldContext);
  if (!fieldContext) throw new Error('useFormField must be used within <FormField>');

  const { getFieldState, formState } = useFormContext();
  const fieldState = getFieldState(fieldContext.name, formState);

  return {
    name: fieldContext.name,
    formItemId:        `${fieldContext.name}-form-item`,
    formDescriptionId: `${fieldContext.name}-form-description`,
    formMessageId:     `${fieldContext.name}-form-message`,
    ...fieldState,
  };
}

function FormItem({ className, ref, ...props }: React.ComponentProps<'div'>) {
  return <div ref={ref} className={cn('space-y-2', className)} {...props} />;
}
FormItem.displayName = 'FormItem';

function FormLabel({
  className,
  ref,
  ...props
}: React.ComponentProps<typeof LabelPrimitive.Root>) {
  const { error, formItemId } = useFormField();
  return (
    <Label
      ref={ref}
      className={cn(error && 'text-destructive', className)}
      htmlFor={formItemId}
      {...props}
    />
  );
}
FormLabel.displayName = 'FormLabel';

function FormControl({ ref, ...props }: React.ComponentProps<typeof Slot>) {
  const { error, formItemId, formDescriptionId, formMessageId } = useFormField();
  return (
    <Slot
      ref={ref}
      id={formItemId}
      aria-describedby={error ? `${formDescriptionId} ${formMessageId}` : formDescriptionId}
      aria-invalid={!!error}
      {...props}
    />
  );
}
FormControl.displayName = 'FormControl';

function FormMessage({ className, children, ref, ...props }: React.ComponentProps<'p'>) {
  const { error, formMessageId } = useFormField();
  const body = error ? String(error?.message) : children;
  if (!body) return null;
  return (
    <p
      ref={ref}
      id={formMessageId}
      className={cn('text-sm font-medium text-destructive', className)}
      {...props}
    >
      {body}
    </p>
  );
}
FormMessage.displayName = 'FormMessage';

export { Form, FormControl, FormField, FormItem, FormLabel, FormMessage, useFormField };
