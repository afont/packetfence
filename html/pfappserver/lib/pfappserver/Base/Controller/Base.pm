package pfappserver::Base::Controller::Base;
=head1 NAME

/usr/local/pf/html/pfappserver/lib/pfappserver/Base/Controller add documentation

=cut

=head1 DESCRIPTION

Base 

=cut

use strict;
use warnings;
use Date::Parse;
use HTTP::Status qw(:constants is_error is_success);
use Moose;
use namespace::autoclean;
use POSIX;
use URI::Escape;
use pfappserver::Base::Action::SimpleSearch;

use pf::authentication;
use pf::os;
use pf::util qw(load_oui download_oui);
# imported only for the $TIME_MODIFIER_RE regex. Ideally shouldn't be 
# # imported but it's better than duplicating regex all over the place.
use pf::config;
use pfappserver::Form::Config::Pf;
use Moose;
use Class::MOP;
use Catalyst::Utils;
use Moose::Meta::Class;
use String::RewritePrefix 0.004;
use MooseX::Types::Moose qw/ArrayRef Str RoleName/;
use List::Util qw(first);

BEGIN {extends 'Catalyst::Controller'; }

=head1 METHODS

=cut

=head2 auto

Allow only authenticated users

=cut

sub auto :Private {
    my ($self, $c) = @_;

    unless ($c->user_exists()) {
        $c->response->status(HTTP_UNAUTHORIZED);
        $c->response->location($c->req->referer);
        $c->stash->{template} = 'admin/unauthorized.tt';
        $c->detach();
        return 0;
    }

    return 1;
}

our %VALID_PARAMS = (
    page_num => 1,
    by => 1,
    direction => 1,
    filter => 1,
    start => 1,
    end => 1,
);

sub valid_param {
    my ($self,$key) = @_;
    return exists $VALID_PARAMS{$key};
}
sub _parse_SimpleSearch_attr {
    my ( $self, $c, $name, $value ) = @_;
    return SimpleSearch => $value;
}

around create_action => sub {
    my ($orig, $self, %args) = @_;
    return $self->$orig(%args)
        if $args{name} =~ /^_(DISPATCH|BEGIN|AUTO|ACTION|END)$/;
    my ($model) = @{$args{attributes}->{SimpleSearch}|| []};
    return $self->$orig(%args) unless $model;
    return Base::Action::SimpleSearch->new( \%args  );
};

=head2 _list_items

=cut

sub _list_items {
    my ( $self, $c, $model_name ) = @_;
    my ( $filter, $orderby, $orderdirection, $status, $result, $items_ref );
    my $model       = $c->model($model_name);
    my $field_names = $model->field_names();
    my $page_num    = $c->stash->{'page_num'} || 1;
    my $per_page    = $c->stash->{'per_page'} || 25;
    my $limit_clause =
        "LIMIT " . ( ( $page_num - 1 ) * $per_page ) . "," . $per_page;
    my %params = ( limit => $limit_clause );

    if ( exists( $c->stash->{'filter'} ) ) {
        $filter = $c->stash->{'filter'};
        $params{'where'} = { type => 'any', like => $filter };
        $c->stash->{filter} = $filter;
    }
    if ( exists( $c->stash->{'by'} ) ) {
        $orderby = $c->stash->{'by'};
        if ( grep { $_ eq $orderby } (@$field_names) ) {
            $orderdirection = $c->stash->{'direction'};
            unless ( defined $orderdirection && grep { $_ eq $orderdirection } ( 'asc', 'desc' ) ) {
                $orderdirection = 'asc';
            }
            $params{'orderby'}     = "ORDER BY $orderby $orderdirection";
            $c->stash->{by}        = $orderby;
            $c->stash->{direction} = $orderdirection;
        }
    }
    my $count;
    ( $status, $result ) = $model->search(%params);
    if ( is_success($status) ) {
        $items_ref = $result;
       ( $status, $count ) = $model->countAll(%params);
    }
    if ( is_success($status) ) {
        $items_ref = $result;
        $c->stash->{count}       = $count;
        $c->stash->{page_num}    = $page_num;
        $c->stash->{per_page}    = $per_page;
        $c->stash->{by}          = $orderby || $field_names->[0];
        $c->stash->{direction}   = $orderdirection || 'asc';
        $c->stash->{items}       = $items_ref;
        $c->stash->{field_names} = $field_names;
        $c->stash->{pages_count} = ceil( $count / $per_page );
    }
    else {
        $c->response->status($status);
        $c->stash->{status_msg}   = $result;
        $c->stash->{current_view} = 'JSON';
    }
}
 
=back

=head1 AUTHOR

YOUR NAME <flachapelle@inverse.ca>

=head1 COPYRIGHT

Copyright (C) 2012 Inverse inc.

=head1 LICENSE

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301,
USA.

=cut

1;
