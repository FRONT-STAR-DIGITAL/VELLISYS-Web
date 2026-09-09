"use client";

import { useParams } from "next/navigation";
import { notFound } from "next/navigation";
import { IssueForm } from "@/components/issue-form";
import { BOOK_META, bookFromSlug, formatBookNumber } from "@/lib/books";
import { nextSequence } from "@/lib/ledgers";
import { useBooks } from "@/lib/store";

export default function NewPage() {
  const params = useParams<{ book: string }>();
  const book = bookFromSlug(params.book);
  const { state } = useBooks();
  if (!book) notFound();
  const meta = BOOK_META[book];
  const number = formatBookNumber(book, state.company.booksYear, nextSequence(state, book));

  return (
    <div className="mx-auto max-w-3xl">
      <p className="text-[11px] uppercase tracking-[0.22em] text-stamp">
        Write the next page
      </p>
      <h1 className="font-heading text-3xl">{meta.verb}</h1>
      <p className="mt-1 text-sm text-muted-foreground">
        {meta.title} · {number} will be issued as original, duplicate and counterfoil.
      </p>
      <div className="mt-8">
        <IssueForm book={book} />
      </div>
    </div>
  );
}
